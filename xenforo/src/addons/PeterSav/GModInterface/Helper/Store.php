<?php

namespace PeterSav\GModInterface\Helper;

class Store {
	public function processPlayerStoreRankComplete($purchaserForumId, $receiverForumId, $packageId, $transactionId) {
		if (is_null($purchaserForumId) or is_null($receiverForumId)) {
			throw new \ErrorException('Attempt to process player store rank purchase with null steamid64 or forumid.');
		}

		$db = \XF::db();

		$expires = 0;
		$groupArray = $this->fetchStoreRankByID($packageId);

		if (!$groupArray || $groupArray === 0) {
			throw new \ErrorException('Invalid package ID: ' . $packageId . '. Rank not found in database.');
		}

		$giftedRankBanner = $this->getGroupBanner($groupArray["group_id"]);
		$isGift = 0; // MySQL only stores bools with BIT column type as 0 for false and 1 for true

		if ($purchaserForumId != $receiverForumId) {
			$isGift = 1;
		}

		if ($isGift != 0) {
			// This rank purchase is a gift, add to selected user_id
			$finder = \XF::finder('XF:User');
			$userEntity = $finder->where('user_id', $receiverForumId)->fetchOne();

			$this->getUserGroupChangeService()->addUserGroupChange($receiverForumId, 'gmodInterfacerGroupChange-' . $transactionId, $groupArray["group_id"]);

			$alertRepo = \XF::repository('XF:UserAlert');
			$finder = \XF::finder('XF:User');

			$gifterEntity = $finder->where('user_id', $purchaserForumId)->fetchOne();

			$alertRepo->alert($gifterEntity, $gifterEntity->user_id, $gifterEntity->username, 'user', $gifterEntity->user_id, 'store_giftsent', [
				'to_gift_user' => $userEntity,
				'to_gift_username' => $userEntity->username,
				'rank_banner' => $giftedRankBanner
			]);

			$gifteeAlertParams = array(
				'from_user' => $gifterEntity,
				'from_username' => $gifterEntity->username,
				'rank_banner' => $giftedRankBanner
			);

			$alertRepo = \XF::repository('XF:UserAlert');
			$alertRepo->alert($userEntity, $userEntity->user_id, $userEntity->username, 'user', $userEntity->user_id, 'store_giftreceive', $gifteeAlertParams);
		} else {
			$finder = \XF::finder('XF:User');
			$userEntity = $finder->where('user_id', $purchaserForumId)->fetchOne();

			$this->getUserGroupChangeService()->addUserGroupChange($purchaserForumId, 'gmodInterfacerGroupChange-' . $transactionId, $groupArray["group_id"]);

			$alertPrograms = array(
				'rank_banner' => $giftedRankBanner
			);

			$alertRepo = \XF::repository('XF:UserAlert');
			$alertRepo->alert($userEntity, $userEntity->user_id, $userEntity->username, 'user', $userEntity->user_id, 'store_processed', $alertPrograms);
		}

		$storeRankRow = $db->fetchRow('SELECT * FROM gmod_store_rank_groups WHERE `upgrade_id` = ? ', $packageId);

		if ($storeRankRow["length"] == 0) {
			// If new rank is permanent, we need to check if the user has any existing permanent ranks and remove them
			// God help this code

			$usersPermanentRanks = $db->fetchAll("
        SELECT
          active_ranks.*,
          rank_details.group_id,
          rank_details.upgrade_id as rank_upgrade_id
        FROM gmod_store_rank_active AS active_ranks
        JOIN gmod_store_rank_groups AS rank_details
          ON active_ranks.upgrade_group_id = rank_details.upgrade_id
        WHERE active_ranks.receiver_user_id = ?
          AND active_ranks.expires = 0
          AND rank_details.length = 0
      ", $receiverForumId);

			if ($usersPermanentRanks) {
				$finder = \XF::finder('XF:User');
				$userEntity = $finder->where('user_id', $receiverForumId)->fetchOne();

				foreach ($usersPermanentRanks as $oldPermanentRank) {
					$userEntity->removeUserFromGroup($oldPermanentRank['group_id']);

					$db->insert('gmod_store_rank_expired', [
						'receiver_user_id' => $oldPermanentRank['receiver_user_id'],
						'purchaser_user_id' => $oldPermanentRank['purchaser_user_id'],
						'transaction_id' => $oldPermanentRank['transaction_id'],
						'upgrade_group_id' => $oldPermanentRank['upgrade_group_id'],
						'is_gift' => $oldPermanentRank['is_gift'],
						'purchased' => $oldPermanentRank['purchased'],
						'expires' => $oldPermanentRank['expires'],
						'expired' => time()
					]);

					$db->delete('gmod_store_rank_active', 'upgrade_id = ?', $oldPermanentRank['upgrade_id']);
				}

				$userEntity->save();
			}
		}

		if ($storeRankRow["length"] > 0 && !empty($storeRankRow["length_unit"])) {
			switch ($storeRankRow["length_unit"]) {
				case 'day':
					$expires = time() + ($storeRankRow["length"] * 86400); // 24 hours = 86400 seconds
					break;
				case 'month':
					$expires = time() + ($storeRankRow["length"] * 2592000); // 30 days = 2592000 seconds
					break;
				case 'year':
					$expires = time() + ($storeRankRow["length"] * 31536000); // 365 days = 31536000 seconds
					break;
				default:
					$expires = 0; // Permanent if length_unit is empty or unknown
					break;
			}
		}

		$db->insert('gmod_store_rank_active', [
			'receiver_user_id' => $receiverForumId,
			'purchaser_user_id' => $purchaserForumId,
			'transaction_id' => $transactionId,
			'upgrade_group_id' => $packageId,
			'is_gift' => $isGift,
			'purchased' => time(),
			'expires' => $expires
		]);

		return;
	}

	public function getAccessToken() {
		$PAYPAL_CLIENT_ID;
		$PAYPAL_SECRET;
		$CURL_URL;

		$options = \XF::options();

		if ($options->gmod_interfacer_store_production_check) {
			$PAYPAL_CLIENT_ID = $options->gmod_interfacer_store_production_client_id;
			$PAYPAL_SECRET = $options->gmod_interfacer_store_production_secret_key;

			$CURL_URL = "https://api-m.paypal.com/v1/oauth2/token";
		} else {
			$PAYPAL_CLIENT_ID = $options->gmod_interfacer_store_sandbox_client_id;
			$PAYPAL_SECRET = $options->gmod_interfacer_store_sandbox_secret_key;

			$CURL_URL = "https://api-m.sandbox.paypal.com/v1/oauth2/token";
		}

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $CURL_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_USERPWD => $PAYPAL_CLIENT_ID . ":" . $PAYPAL_SECRET,
			CURLOPT_POSTFIELDS => "grant_type=client_credentials",
			CURLOPT_HTTPHEADER => array(
				"Accept: application/json",
				"Accept-Language: en_US"
			),
		));

		$result = curl_exec($curl);
		curl_close($curl);

		$array = json_decode($result, true);

		return $array['access_token'];
	}

	public function getPayPalOrderInfo($orderId) {
		$accessToken = $this->getAccessToken();
		$options = \XF::options();

		if ($options->gmod_interfacer_store_production_check) {
			$url = "https://api-m.paypal.com/v2/payments/captures/{$orderId}";
		} else {
			$url = "https://api-m.sandbox.paypal.com/v2/payments/captures/{$orderId}";
		}

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Authorization: Bearer ' . $accessToken,
			'Accept: application/json',
			'Content-Type: application/json'
		));
		$response = json_decode(curl_exec($curl), true);
		curl_close($curl);

		return $response;
	}

	public function fetchStoreRankByID($id) {
		$db = \XF::db();
		$groupArray = $db->fetchRow('SELECT * FROM gmod_store_rank_groups WHERE `upgrade_id` = ' . $id . ' LIMIT 1');

		if (!is_null($groupArray)) {
			return $groupArray;
		}

		return 0;
	}

	public function userActiveStoreRank($userId) {
		$db = \XF::db();
		$userActiveStoreRank = $db->fetchOne('SELECT upgrade_group_id FROM gmod_store_rank_active WHERE receiver_user_id = ? ORDER BY upgrade_id DESC', $userId);

		return $userActiveStoreRank;
	}

	public function getGroupBanner($groupId) {
		$db = \XF::db();
		$groupData = $db->fetchRow('SELECT * FROM xf_user_group WHERE `user_group_id` = ? ', $groupId);

		if (!$groupData || empty($groupData)) {
			throw new \ErrorException('Group ID ' . $groupId . ' not found in user groups table.');
		}

		return "<em class=\"userBanner {$groupData['banner_css_class']} \"<span class=\"userBanner-before\"></span><strong>{$groupData['banner_text']}</strong><span class=\"userBanner-after\"></span></em>";
	}

	/**
	 * Calculate the upgrade price for a user purchasing a new rank.
	 * If user has an existing rank, they pay the difference between the new rank price and their current rank price.
	 *
	 * @param int $userId The User ID of the purchaser
	 * @param int $newRankUpgradeId upgrade_id of the rank being purchased
	 * @param bool $isGift Whether purchase is being gifted (gifts always pay full price)
	 * @return array ['price' => float, 'is_upgrade' => bool, 'current_rank_price' => float]
	 */
	public function calculateUpgradePrice($userId, $newRankUpgradeId) {
		$db = \XF::db();


		$newRank = $this->fetchStoreRankByID($newRankUpgradeId);

		if (!$newRank || $newRank === 0) {
			return ['price' => 0, 'is_upgrade' => false, 'current_rank_price' => 0, 'error' => 'Invalid rank'];
		}

		$basePrice = (float)$newRank['price'];

		// Find user's current highest priority rank and its price
		$currentRankData = $db->fetchRow("
      SELECT srg.upgrade_id, srg.price, srg.rank_priority, srg.group_id
      FROM gmod_store_rank_groups srg
      INNER JOIN xf_user_group_relation ugr ON ugr.user_group_id = srg.group_id
      WHERE ugr.user_id = ?
      AND srg.rank_priority > 0
      ORDER BY srg.rank_priority DESC
      LIMIT 1
    ", [$userId]);

		// If user has no current rank, pay full price
		if (!$currentRankData) {
			return ['price' => $basePrice, 'is_upgrade' => false, 'current_rank_price' => 0];
		}

		$currentRankPriority = (int)$currentRankData['rank_priority'];
		$currentRankPrice = (float)$currentRankData['price'];
		$newRankPriority = (int)$newRank['rank_priority'];

		// Only apply upgrade pricing if the new rank is higher priority than current
		if ($newRankPriority > $currentRankPriority && $currentRankPrice > 0) {
			$upgradePrice = max(0, $basePrice - $currentRankPrice);
			return [
				'price' => $upgradePrice,
				'is_upgrade' => true,
				'current_rank_price' => $currentRankPrice,
				'base_price' => $basePrice
			];
		}

		// New rank is same or lower priority, pay full price (shouldn't happen due to frontend validation)
		return ['price' => $basePrice, 'is_upgrade' => false, 'current_rank_price' => $currentRankPrice];
	}

	/**
	 * @return \XF\Service\User\UserGroupChange
	 */
	protected function getUserGroupChangeService() {
		return \XF::app()->service('XF:User\UserGroupChange');
	}
}
