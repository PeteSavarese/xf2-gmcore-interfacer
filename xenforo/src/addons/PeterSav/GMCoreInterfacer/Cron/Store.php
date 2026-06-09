<?php

namespace PeterSav\GModInterface\Cron;

class Store {
	public static function run() {
		$app = \XF::app();
		$db = \XF::db();

		$allActive = $db->fetchAll("SELECT * FROM gmod_store_rank_active WHERE expires > 0");

		foreach ($allActive as $key => $storeRank) {
			if (time() > $storeRank["expires"] and $storeRank["expires"] !== 0) { // Expire 0 for ranks that don't expire
				$db->insert('gmod_store_rank_expired', [
					'purchaser_user_id' => $storeRank["purchaser_user_id"],
					'receiver_user_id' => $storeRank["receiver_user_id"],
					'transaction_id' => $storeRank["transaction_id"],
					'upgrade_group_id' => $storeRank["upgrade_group_id"],
					'is_gift' => $storeRank["is_gift"],
					'purchased' => $storeRank["purchased"],
					'expires' => $storeRank["expires"],
					'expired' => $storeRank["expires"]
				]);

				$userService = $app->service('XF:User\UserGroupChange');
				$userService->removeUserGroupChange($storeRank["receiver_user_id"], 'gmodInterfacerGroupChange-' . $storeRank["transaction_id"]);

				// Alert rank receiver that their rank has expired
				$alertRepo = \XF::repository('XF:UserAlert');
				$userFinder = \XF::finder('XF:User');

				$receiverEntity = $userFinder->where('user_id', $storeRank["receiver_user_id"])->fetchOne();

				$groupArray = self::fetchStoreRankByID($storeRank["upgrade_group_id"]);

				$alertParams = array(
					'rank_banner' => self::getGroupBanner($groupArray["group_id"])
				);

				$alertRepo = \XF::repository('XF:UserAlert');
				$alertRepo->alert($receiverEntity, $receiverEntity->user_id, $receiverEntity->username, 'user', $receiverEntity->user_id, 'store_rank_expired', $alertParams);

				$db->query("DELETE FROM gmod_store_rank_active WHERE upgrade_id = ?", $storeRank["upgrade_id"]);
			}
		}
	}

	public static function fetchStoreRankByID($id) {
		$db = \XF::db();
		$groupArray = $db->fetchRow('SELECT * FROM gmod_store_rank_groups WHERE `upgrade_id` = ' . $id . ' LIMIT 1');

		if (!is_null($groupArray)) {
			return $groupArray;
		}

		return 0;
	}

	public static function getGroupBanner($groupId) {
		$db = \XF::db();
		$groupId = $db->fetchRow('SELECT * FROM xf_user_group WHERE `user_group_id` = ? ', $groupId);

		return "<em class=\"userBanner {$groupId['banner_css_class']} \"<span class=\"userBanner-before\"></span><strong>{$groupId['banner_text']}</strong><span class=\"userBanner-after\"></span></em>";
	}
}
