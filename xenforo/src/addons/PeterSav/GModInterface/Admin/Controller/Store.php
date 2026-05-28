<?php

namespace PeterSav\GModInterface\Admin\Controller;

use XF;
use XF\Mvc\ParameterBag;
use XF\Repository\UserGroupRepository;

class Store extends \XF\Admin\Controller\AbstractController {
	protected function preDispatchController($action, ParameterBag $params) {
		$this->assertAdminPermission('gmod_manage_store_ranks');
	}

	private function assertStoreRankExists($groupId) {
		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $repo */
		$repo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$group = $repo->getByUpgradeId((int)$groupId);

		if (!$group) {
			return false;
		}

		return $group;
	}

	public function actionIndex() {
		$viewParams = array(
			"test" => true
		);

		return $this->view('', 'gmod_store_landing', $viewParams);
	}

	public function actionManage() {
		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $repo */
		$repo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$groups = $repo->findAllOrdered()->fetch();

		// Presentational trim of title (do not persist)
		foreach ($groups as $group) {
			$shortTitle = explode('<br>', (string)$group->title)[0];
			$group->title = $shortTitle; // temporary for output only
		}

		$viewParams = array(
			'groups' => $groups
		);

		return $this->view('', 'gmod_admin_store_groups_list', $viewParams);
	}

	public function actionAdd() {
		/** @var \PeterSav\GModInterface\Entity\Store\RankGroup $group */
		$group = $this->em()->create('PeterSav\\GModInterface:Store\\RankGroup');
		return $this->view('', 'gmod_admin_store_editgroup', [
			'group' => $group,
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		]);
	}

	public function actionEditgroup(ParameterBag $params) {
		if (!$params->group_id) {
			return $this->error("Invalid store rank ID supplied! Please provide a store rank ID.");
		}

		$groupRow = $this->assertStoreRankExists($params->group_id);

		if (!$groupRow) {
			return $this->error("Invalid store rank ID supplied! Rank ID does not exist in database!");
		}

		return $this->view('', 'gmod_admin_store_editgroup', [
			"group" => $groupRow,
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		]);
	}

	public function actionSave(ParameterBag $params) {
		$this->assertPostOnly();

		$input = $this->filter([
			"title" => "str",
			"description" => "str",
			"rank_image" => "str",
			"display_order" => "uint",
			"group_id" => "uint",
			"price" => "unum",
			"length_type" => "str",
			"length" => "uint",
			"length_unit" => "str",
			"can_purchase" => "bool",
		]);

		if (empty($input["title"])) {
			return $this->error("Title is required.");
		}

		if (!$input["group_id"]) {
			return $this->error("User group selection is required.");
		}

		if ($input["price"] <= 0) {
			return $this->error("Price must be greater than 0.");
		}

		if ($input["length_type"] === "permanent") {
			$input["length"] = 0;
			$input["length_unit"] = "";
		} else if ($input["length_type"] === "timed") {
			if (!$input["length"]) {
				$input["length"] = 1;
			}
			if (!$input["length_unit"]) {
				$input["length_unit"] = "month";
			}

			// Match DB enum: ['day','month','year','']
			$validUnits = ['day', 'month', 'year'];
			if (!in_array($input["length_unit"], $validUnits)) {
				return $this->error("Invalid length unit selected.");
			}
		}

		try {
			if ($params->group_id) {
				// Update existing entity
				$group = $this->assertStoreRankExists($params->group_id);

				if (!$group) {
					return $this->error("Invalid store rank ID supplied! Rank ID does not exist in database!");
				}

				$group->title = $input['title'];
				$group->description = $input['description'];
				$group->rank_image = $input['rank_image'];
				$group->rank_priority = $input['display_order'];
				$group->group_id = $input['group_id'];
				$group->price = $input['price'];
				$group->length = $input['length'];
				$group->length_unit = $input['length_unit'];
				$group->can_purchase = $input['can_purchase'] ? 1 : 0;
				$group->save();

				return $this->redirect($this->buildLink('gl-store/manage') . $this->buildLinkHash($group->upgrade_id));
			} else {
				// Create new entity
				/** @var \PeterSav\GModInterface\Entity\Store\RankGroup $group */
				$group = $this->em()->create('PeterSav\\GModInterface:Store\\RankGroup');
				$group->title = $input['title'];
				$group->description = $input['description'];
				$group->rank_image = $input['rank_image'];
				$group->rank_priority = $input['display_order'];
				$group->group_id = $input['group_id'];
				$group->price = $input['price'];
				$group->length = $input['length'];
				$group->length_unit = $input['length_unit'];
				$group->can_purchase = $input['can_purchase'] ? 1 : 0;
				$group->save();

				return $this->redirect($this->buildLink('gl-store/manage') . $this->buildLinkHash($group->upgrade_id));
			}
		} catch (\Exception $e) {
			\XF::logException($e);
			return $this->error("An error occurred while saving the store rank. Please try again.");
		}
	}

	public function actionDelete(ParameterBag $params) {
		if (!$params->group_id) {
			return $this->error("Invalid store rank ID supplied!");
		}

		$groupRow = $this->assertStoreRankExists($params->group_id);

		if (!$groupRow) {
			return $this->error("Store rank not found!");
		}

		if ($this->isPost()) {
			try {
				$groupRow->delete();

				return $this->redirect($this->buildLink("gl-store/manage"));
			} catch (\Exception $e) {
				\XF::logException($e);
				return $this->error("An error occurred while deleting the store rank.");
			}
		} else {
			$viewParams = [
				'group' => $groupRow
			];

			return $this->view('', 'gmod_admin_store_delete_confirm', $viewParams);
		}
	}

	public function actionPurchases(ParameterBag $params) {
		$perPage = 25;
		$page = $this->filterPage($params->page);

		$finder = $this->finder('PeterSav\\GModInterface:Store\\Transaction')
			->order('transaction_time', 'DESC');

		$total = $finder->total();
		$transactions = $finder->limitByPage($page, $perPage)->fetch();

		$list = [];
		foreach ($transactions as $txn) {
			$log = json_decode((string)$txn->transaction_log, true) ?: [];
			$purchaser = $this->em()->find('XF:User', (int)$txn->purchaser_user_id);
			$receiver = null;

			if ($txn->receiver_user_id && (int)$txn->receiver_user_id !== (int)$txn->purchaser_user_id) {
				$receiver = $this->em()->find('XF:User', (int)$txn->receiver_user_id);
			}

			$list[] = [
				// Use external transaction id if available; fall back to PayPal order id
				'transaction_id' => $log['paypal_order_id'],
				'transaction_time' => $txn->transaction_time,
				'transaction_log' => $txn->transaction_log,
				'user_ent' => $purchaser,
				'receiver_user_ent' => $receiver,
			];
		}

		$viewParams = array(
			'total' => $total,
			'transactions' => $list,
			'perPage' => $perPage,
			'page' => $page,
		);

		return $this->view('', 'gmod_admin_store_purchases', $viewParams);
	}

	public function actionPurchasesView(ParameterBag $params) {
		$transactionId = $this->filter('transaction_id', 'str');

		if (!$transactionId) {
			return $this->error("Missing transaction ID in request.");
		}

		if (!preg_match('/^[A-Z0-9]{17}$/', $transactionId)) {
			return $this->error("Invalid transaction ID format.");
		}

		try {
			// Locate matching transaction record before reaching out to PayPal so we can determine the correct order ID
			$finder = $this->finder('PeterSav\\GModInterface:Store\\Transaction');
			$like = '%"transaction_id":"' . \XF::db()->escapeLike($transactionId) . '"%';
			$txn = $finder->where('transaction_log', 'LIKE', $like)->fetchOne();

			if (!$txn) {
				// fallback to paypal_order_id
				$finder = $this->finder('PeterSav\\GModInterface:Store\\Transaction');
				$likeOrder = '%"paypal_order_id":"' . \XF::db()->escapeLike($transactionId) . '"%';
				$txn = $finder->where('transaction_log', 'LIKE', $likeOrder)->fetchOne();
			}

			if (!$txn) {
				return $this->error("Transaction not found in database.");
			}

			$transactionLog = json_decode((string)$txn->transaction_log, true);
			if (!is_array($transactionLog)) {
				\XF::logError("Malformed transaction log data for transaction ID: " . $transactionId);
				$transactionLog = [];
			}

			// Fetch from PayPal via service - tries capture first, then falls back to order
			$paypalConfig = $this->getPayPalConfig();

			/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
			$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
			$token = $paypal->getAccessToken();

			if (isset($token['error']) || empty($token['access_token'])) {
				return $this->error('Invalid PayPal client credentials.');
			}

			$transactionInfoPP = $paypal->fetchOrder($transactionId, $token['access_token']);

			if (!$transactionInfoPP) {
				return $this->error("Failed to retrieve PayPal transaction information.");
			}

			if (!empty($transactionInfoPP['name']) && $transactionInfoPP['name'] === 'RESOURCE_NOT_FOUND') {
				return $this->error("Invalid transaction ID supplied; transaction does not exist on PayPal.");
			}

			$groupInfo = false;
			if (!empty($transactionLog['rank_id'])) {
				$groupInfo = $this->assertStoreRankExists($transactionLog['rank_id']);
			}

			$isGift = !empty($transactionLog['is_gift']);
			$discountAmount = null;
			$discountPercent = null;

			if ($groupInfo && isset($transactionLog['amount'])) {
				$basePrice = (float)$groupInfo->price;
				$paid = (float)$transactionLog['amount'];
				$diff = $basePrice - $paid;

				if ($diff > 0.009) { // small tolerance to ignore rounding noise
					$discountAmount = round($diff, 2);
					$discountPercent = $basePrice > 0 ? round(($diff / $basePrice) * 100, 1) : null;
				}
			}

			$viewParams = [
				'transactionInfoDb' => $txn,
				'transactionLog' => $transactionLog,
				'groupInfo' => $groupInfo,
				'transactionInfoPP' => $transactionInfoPP,
				'preUpdatePurchase' => ($txn->transaction_time < 1638217910),
				'isGift' => $isGift,
				'discountAmount' => $discountAmount,
				'discountPercent' => $discountPercent,
				'showDebug' => $this->filter('debug', 'bool', false) && \XF::$debugMode
			];

			return $this->view('', 'gmod_admin_store_purchases_view', $viewParams);

		} catch (\Exception $e) {
			\XF::logException($e);
			return $this->error("An error occurred while retrieving transaction information.");
		}
	}

	public function actionRecovery() {
		/** @var \PeterSav\GModInterface\Repository\Store\RecoveryQueue $repo */
		$repo = $this->repository('PeterSav\\GModInterface:Store\\RecoveryQueue');
		$entries = $repo->findAllOrdered()->fetch();

		$enriched = [];
		foreach ($entries as $entry) {
			$purchaser = $entry->purchaser_user_id
				? $this->em()->find('XF:User', (int)$entry->purchaser_user_id)
				: null;
			$receiver = $entry->receiver_user_id
				? $this->em()->find('XF:User', (int)$entry->receiver_user_id)
				: null;
			$rankGroup = $entry->rank_id
				? $this->assertStoreRankExists($entry->rank_id)
				: null;

			$enriched[] = [
				'entry'      => $entry,
				'purchaser'  => $purchaser,
				'receiver'   => $receiver,
				'rank_group' => $rankGroup,
			];
		}

		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$allRanks = $groupRepo->findAllOrdered()->fetch();

		return $this->view('', 'gmod_admin_store_recovery', [
			'entries'  => $enriched,
			'allRanks' => $allRanks,
		]);
	}

	public function actionRecoveryadd() {
		$this->assertPostOnly();

		$captureId      = trim($this->filter('paypal_capture_id', 'str'));
		$payerEmail     = trim($this->filter('payer_email', 'str'));
		$transactionDate = $this->filter('transaction_date', 'str');

		if (empty($captureId) || empty($payerEmail)) {
			return $this->error('PayPal capture ID and payer email are required.');
		}

		if (!preg_match('/^[A-Z0-9]{17}$/', $captureId)) {
			return $this->error('Invalid capture ID format. Expected 17 uppercase alphanumeric characters.');
		}

		/** @var \PeterSav\GModInterface\Repository\Store\RecoveryQueue $repo */
		$repo = $this->repository('PeterSav\\GModInterface:Store\\RecoveryQueue');

		if ($repo->isDuplicateCaptureId($captureId)) {
			return $this->error('This capture ID is already in the recovery queue.');
		}

		$parsedDate = $transactionDate ? (int)strtotime($transactionDate) : time();
		if ($parsedDate <= 0) {
			$parsedDate = time();
		}

		$paypalConfig = $this->getPayPalConfig();
		/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
		$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
		$token  = $paypal->getAccessToken();

		if (isset($token['error']) || empty($token['access_token'])) {
			return $this->error('PayPal authentication failed. Check credentials in store options.');
		}

		$captureData = $paypal->fetchTransactionInfo($captureId, $token['access_token']);

		if (empty($captureData) || $captureData['_status'] !== 200) {
			$msg = $captureData['message'] ?? ('HTTP ' . ($captureData['_status'] ?? '?'));
			return $this->error('PayPal fetch failed: ' . $msg);
		}

		$repo->createFromPaypal($captureId, $payerEmail, $parsedDate, $captureData);

		return $this->redirect($this->buildLink('gl-store/recovery'));
	}

	public function actionRecoveryapply() {
		$this->assertPostOnly();

		$entryId        = $this->filter('entry_id', 'uint');
		$receiverName   = trim($this->filter('receiver_username', 'str'));
		$purchaserName  = trim($this->filter('purchaser_username', 'str'));
		$rankIdOverride = $this->filter('rank_id_override', 'uint');
		$action         = $this->filter('apply_action', 'str');

		/** @var \PeterSav\GModInterface\Entity\Store\RecoveryQueue $entry */
		$entry = $this->em()->find('PeterSav\\GModInterface:Store\\RecoveryQueue', $entryId);
		if (!$entry) {
			return $this->error('Recovery queue entry not found.');
		}

		if ($entry->status !== 'pending') {
			return $this->error('This entry has already been ' . $entry->status . '.');
		}

		if ($action === 'skip') {
			$entry->status = 'skipped';
			$entry->save();
			return $this->redirect($this->buildLink('gl-store/recovery'));
		}

		// Resolve purchaser
		$purchaserUserId = (int)$entry->purchaser_user_id;
		if ($purchaserName) {
			$purchaserUser = \XF::app()->em()->findOne('XF:User', ['username' => $purchaserName]);
			if (!$purchaserUser) {
				return $this->error('Purchaser username not found: ' . $purchaserName);
			}
			$purchaserUserId = (int)$purchaserUser->user_id;
		}

		if (!$purchaserUserId) {
			return $this->error('Purchaser must be assigned before applying.');
		}

		// Resolve receiver
		if (empty($receiverName)) {
			return $this->error('Receiver username is required.');
		}
		$receiverUser = \XF::app()->em()->findOne('XF:User', ['username' => $receiverName]);
		if (!$receiverUser) {
			return $this->error('Receiver username not found: ' . $receiverName);
		}
		$receiverUserId = (int)$receiverUser->user_id;

		// Resolve rank
		$rankId = $rankIdOverride ?: (int)$entry->rank_id;
		if (!$rankId) {
			return $this->error('A rank must be selected before applying.');
		}

		$rankGroup = $this->assertStoreRankExists($rankId);
		if (!$rankGroup) {
			return $this->error('Rank ID ' . $rankId . ' does not exist.');
		}

		$isGift = ($purchaserUserId !== $receiverUserId);
		$amount = $entry->amount ?? (float)$rankGroup->price;

		/** @var \PeterSav\GModInterface\Service\Store\PurchaseProcessor $processor */
		$processor = $this->service('PeterSav\\GModInterface:Store\\PurchaseProcessor');
		[$ok, $data] = $processor->process([
			'paypal_order_id'    => $entry->paypal_capture_id,
			'rank_id'            => $rankId,
			'recipient_id'       => $receiverUserId,
			'is_gift'            => $isGift,
			'amount'             => $amount,
			'currency'           => 'USD',
			'payer_id'           => '',
			'transaction_id'     => $entry->paypal_capture_id,
			'purchaser_user_id'  => $purchaserUserId,
			'transaction_time'   => (int)$entry->transaction_date,
			'skip_amount_check'  => true,
		]);

		if (!$ok) {
			return $this->error('Apply failed: ' . $data);
		}

		$entry->purchaser_user_id = $purchaserUserId;
		$entry->receiver_user_id  = $receiverUserId;
		$entry->rank_id           = $rankId;
		$entry->status            = 'applied';
		$entry->applied_at        = time();
		$entry->save();

		return $this->redirect($this->buildLink('gl-store/recovery'));
	}

	public function actionManualapply() {
		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$allRanks  = $groupRepo->findAllOrdered()->fetch();

		return $this->view('', 'gmod_admin_store_manual_apply', [
			'allRanks'    => $allRanks,
			'fetchResult' => null,
		]);
	}

	public function actionManualapplyfetch() {
		$this->assertPostOnly();

		$captureId = trim($this->filter('paypal_capture_id', 'str'));

		if (empty($captureId)) {
			return $this->error('PayPal capture ID is required.');
		}

		$paypalConfig = $this->getPayPalConfig();
		/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
		$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
		$token  = $paypal->getAccessToken();

		if (isset($token['error']) || empty($token['access_token'])) {
			return $this->error('PayPal authentication failed.');
		}

		$captureData = $paypal->fetchTransactionInfo($captureId, $token['access_token']);

		if (empty($captureData) || $captureData['_status'] !== 200) {
			$msg = $captureData['message'] ?? ('HTTP ' . ($captureData['_status'] ?? '?'));
			return $this->error('PayPal fetch failed: ' . $msg);
		}

		$rankId = null;
		$customId = $captureData['custom_id'] ?? '';
		if (preg_match('/^rank_(\d+)_\d+$/', $customId, $m)) {
			$rankId = (int)$m[1];
		}

		$amount   = $captureData['amount']['value'] ?? null;
		$rankGroup = $rankId ? $this->assertStoreRankExists($rankId) : null;

		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$allRanks  = $groupRepo->findAllOrdered()->fetch();

		return $this->view('', 'gmod_admin_store_manual_apply', [
			'allRanks'    => $allRanks,
			'fetchResult' => [
				'capture_id' => $captureId,
				'rank_id'    => $rankId,
				'rank_group' => $rankGroup,
				'amount'     => $amount,
				'custom_id'  => $customId,
			],
		]);
	}

	public function actionManualapplyprocess() {
		$this->assertPostOnly();

		$mode            = $this->filter('mode', 'str');
		$captureId       = trim($this->filter('paypal_capture_id', 'str'));
		$rankId          = $this->filter('rank_id', 'uint');
		$receiverName    = trim($this->filter('receiver_username', 'str'));
		$purchaserName   = trim($this->filter('purchaser_username', 'str'));
		$paypalReference = trim($this->filter('paypal_reference', 'str'));

		if (empty($rankId)) {
			return $this->error('A rank must be selected.');
		}

		if (empty($receiverName)) {
			return $this->error('Receiver username is required.');
		}

		$rankGroup = $this->assertStoreRankExists($rankId);
		if (!$rankGroup) {
			return $this->error('Selected rank does not exist.');
		}

		$receiverUser = \XF::app()->em()->findOne('XF:User', ['username' => $receiverName]);
		if (!$receiverUser) {
			return $this->error('Receiver username not found: ' . $receiverName);
		}

		$purchaserUserId = (int)\XF::visitor()->user_id;
		if ($purchaserName) {
			$purchaserUser = \XF::app()->em()->findOne('XF:User', ['username' => $purchaserName]);
			if (!$purchaserUser) {
				return $this->error('Purchaser username not found: ' . $purchaserName);
			}
			$purchaserUserId = (int)$purchaserUser->user_id;
		}

		$receiverUserId = (int)$receiverUser->user_id;
		$isGift = ($purchaserUserId !== $receiverUserId);

		if ($mode === 'paypal' && !empty($captureId)) {
			$transactionId = $captureId;
		} else {
			$transactionId = 'manual_' . $receiverUserId . '_' . time();
			if (!empty($paypalReference)) {
				$transactionId = $paypalReference;
			}
		}

		/** @var \PeterSav\GModInterface\Service\Store\PurchaseProcessor $processor */
		$processor = $this->service('PeterSav\\GModInterface:Store\\PurchaseProcessor');
		[$ok, $data] = $processor->process([
			'paypal_order_id'    => $transactionId,
			'rank_id'            => $rankId,
			'recipient_id'       => $receiverUserId,
			'is_gift'            => $isGift,
			'amount'             => (float)$rankGroup->price,
			'currency'           => 'USD',
			'payer_id'           => '',
			'transaction_id'     => $transactionId,
			'purchaser_user_id'  => $purchaserUserId,
		]);

		if (!$ok) {
			return $this->error('Apply failed: ' . $data);
		}

		return $this->view('', 'gmod_admin_store_manual_apply', [
			'allRanks'    => $this->repository('PeterSav\\GModInterface:Store\\RankGroup')->findAllOrdered()->fetch(),
			'fetchResult' => null,
			'successMsg'  => 'Rank successfully applied to ' . $receiverUser->username . '.',
		]);
	}

	/**
	 * Get PayPal credentials and URLs based on production/sandbox setting
	 * (duplicated from public controller for admin context)
	 */
	private function getPayPalConfig(): array {
		$config = [];

		if ($this->options()->gmod_interfacer_store_production_check) {
			// Production
			$config['client_id'] = $this->options()->gmod_interfacer_store_production_client_id;
			$config['secret'] = $this->options()->gmod_interfacer_store_production_secret_key;
			$config['token_url'] = "https://api-m.paypal.com/v1/oauth2/token";
			$config['order_url_base'] = "https://api-m.paypal.com/v2/checkout/orders/";
		} else {
			// Sandbox
			$config['client_id'] = $this->options()->gmod_interfacer_store_sandbox_client_id;
			$config['secret'] = $this->options()->gmod_interfacer_store_sandbox_secret_key;
			$config['token_url'] = "https://api-m.sandbox.paypal.com/v1/oauth2/token";
			$config['order_url_base'] = "https://api-m.sandbox.paypal.com/v2/checkout/orders/";
		}

		return $config;
	}
}