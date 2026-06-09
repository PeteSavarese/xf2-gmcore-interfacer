<?php

namespace PeterSav\GModInterface\Pub\Controller;

use XF;
use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Store extends AbstractController {
	// Note: Avoid eager-loading repositories in the constructor as XenForo
	// may not have fully initialized extension loaders at that point in some
	// environments. Use $this->repository(...) at call sites instead.

	public function actionIndex(ParameterBag $params) {
		if (!$this->options()->gmod_interfacer_store_active) {
			return $this->view('', 'gmod_store_closed');
		}

		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$fetchGroups = $groupRepo->findAllOrdered()->fetch();

//        $ip = "";

		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		$paypalConfig = $this->getPayPalConfig();

		$viewParams = array(
			'allGroups' => $fetchGroups,
			'isSandbox' => !$this->options()->gmod_interfacer_store_production_check,
			'ipAddress' => $ip,
			'clientId' => $paypalConfig['client_id']
		);

		return $this->view('', 'gmod_store', $viewParams);
	}

	public function actionProcessorder() {
		// $this->assertPostOnly();

		$paypalConfig = $this->getPayPalConfig();
		/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
		$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
		$token = $paypal->getAccessToken();
		if (isset($token['error']) || empty($token['access_token'])) {
			return $this->error('Invalid PayPal client credentials.');
		}
		$accessToken = $token['access_token'];
		$orderId = $this->filter('order_id', 'string');

		if (is_null($orderId) || $orderId == "") {
			return $this->error("An OrderID was not supplied when attempting to show the order process.");
		}

		$response = $paypal->fetchOrder($orderId, $accessToken);

		if (array_key_exists("status", $response) && $response["status"] == "COMPLETED") {
			// Additional validation: verify the payment amount matches the upgrade price. May I just
      // say I LOVE PayPal data structure. We really need to move to Stripe. Thanks PP for being the moral
      // abritrator of processing payments and screwing Steam over
			if (isset($response["purchase_units"][0]["payments"]["captures"][0]["custom_id"])) {
				$customId = json_decode($response["purchase_units"][0]["payments"]["captures"][0]["custom_id"], true);
				$upgradeId = $customId[2];
				$paidAmount = floatval($response["purchase_units"][0]["payments"]["captures"][0]["amount"]["value"]);
				$isGift = $this->filter('is_gift', 'bool');

				/** @var RankGroup $groupRepo */
				$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
				$upgrade = $groupRepo->getByUpgradeId((int)$upgradeId);

				if (!$upgrade) {
					return $this->error("Invalid upgrade ID in payment.");
				}

				// This validation uses purchaser for price calculation as recipient_id is not available in this context
				// The main validation in actionProcesspayment uses recipient_id which is more accurate
				$storeHelper = new \PeterSav\GModInterface\Helper\Store();
				$purchaserId = XF::visitor()->user_id;
				$priceCalculation = $storeHelper->calculateUpgradePrice($purchaserId, $upgradeId, false);
				$expectedPrice = $priceCalculation['price'];

				// Allow for small floating point differences (within 1 cent). We're non-profit so
        // if we lose a cent, so be it
				if (abs($paidAmount - $expectedPrice) > 0.01) {
					return $this->error("Payment amount ($" . number_format($paidAmount, 2) . ") does not match expected upgrade price ($" . number_format($expectedPrice, 2) . "). Payment rejected.");
				}
			}

			return $this->view('', 'gmod_store_processorder', array(
				"paymentFound" => true,
				"orderId" => $orderId,
				"isGift" => $this->filter('is_gift', 'bool'),
				"transactionId" => $response["id"],
				"dumpResponse" => $response
			));
		} else {
			return $this->view('', 'gmod_store_processorder', array(
				"paymentFound" => false,
				"orderId" => $orderId,
				"dumpResponse" => $response
			));
		}
	}

	public function actionCreateorder() {
		$this->assertPostOnly();

		$rankId = $this->filter('rank_id', 'int');
		$recipientId = $this->filter('recipient_id', 'int');
		$isGift = $this->filter('is_gift', 'bool');

		if (empty($rankId) || empty($recipientId)) {
			return $this->error("Missing required order information.");
		}

		// Validate rank exists
		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');

		/** @var \PeterSav\GModInterface\Entity\Store\RankGroup $upgrade */
		$upgrade = $groupRepo->getByUpgradeId((int)$rankId);
		if (!$upgrade) {
			return $this->error('Invalid upgrade ID.');
		}

		// Calculate price
		$storeHelper = new \PeterSav\GModInterface\Helper\Store();
		$priceCalculation = $storeHelper->calculateUpgradePrice($recipientId, $rankId, $isGift);

		if (isset($priceCalculation['error'])) {
			return $this->error('Price calculation error: ' . $priceCalculation['error']);
		}

		$amount = $priceCalculation['price'];
		$orderDescription = "Store Rank: " . $upgrade->title;

		if ($isGift) {
			$orderDescription = "Gift Store Rank: " . $upgrade->title;
		} else if (isset($priceCalculation['is_upgrade']) && $priceCalculation['is_upgrade']) {
			$orderDescription = "Store Rank Upgrade: " . $upgrade->title;
		}

		// Create PayPal order
		$paypalConfig = $this->getPayPalConfig();
		/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
		$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
		$token = $paypal->getAccessToken();
		if (isset($token['error']) || empty($token['access_token'])) {
			return $this->error('PayPal authentication failed');
		}

		$orderData = [
			'intent' => 'CAPTURE',
			'purchase_units' => [[
				'amount' => [
					'currency_code' => 'USD',
					'value' => number_format($amount, 2, '.', '')
				],
				'description' => $orderDescription,
				'custom_id' => "rank_{$rankId}_" . time()
			]]
		];

		$order = $paypal->createOrder($orderData, $token['access_token']);
		if (!isset($order['id'])) {
			return $this->error('Failed to create PayPal order');
		}

		$reply = $this->view();
		$reply->setJsonParams([
			'orderId' => $order['id'],
			'amount' => $amount
		]);
		return $reply;
	}

	public function actionCaptureorder() {
		$this->assertPostOnly();

		$orderId = $this->filter('order_id', 'string');
		$rankId = $this->filter('rank_id', 'int');
		$recipientId = $this->filter('recipient_id', 'int');
		$isGift = $this->filter('is_gift', 'bool');

		if (empty($orderId) || empty($rankId) || empty($recipientId)) {
			return $this->error("Missing required capture information.");
		}

		$paypalConfig = $this->getPayPalConfig();
		/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
		$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
		$token = $paypal->getAccessToken();
		if (isset($token['error']) || empty($token['access_token'])) {
			return $this->error('PayPal authentication failed');
		}

		$captureResult = $paypal->captureOrder($orderId, $token['access_token']);
		if (!isset($captureResult['status']) || $captureResult['status'] !== 'COMPLETED') {
			return $this->error('PayPal capture failed');
		}

		$purchaseUnit = $captureResult['purchase_units'][0];
		$capture = $purchaseUnit['payments']['captures'][0];

		$orderInfo = [
			'paypal_order_id' => $orderId,
			'rank_id' => $rankId,
			'recipient_id' => $recipientId,
			'is_gift' => $isGift,
			'amount' => $capture['amount']['value'],
			'currency' => $capture['amount']['currency_code'],
			'payer_id' => $captureResult['payer']['payer_id'] ?? '',
			'transaction_id' => $capture['id']
		];

		// Process the payment
		return $this->processPaymentOrder($orderInfo, $captureResult);
	}

	private function processPaymentOrder(array $orderInfo, array $captureResult) {
		$rankId = $orderInfo['rank_id'];
		$recipientId = $orderInfo['recipient_id'];
		$isGift = $orderInfo['is_gift'];
		$amount = $orderInfo['amount'];
		$currency = $orderInfo['currency'];
		$transactionId = $orderInfo['transaction_id'];
		$paypalOrderId = $orderInfo['paypal_order_id'];
		$payerId = $orderInfo['payer_id'];

		/** @var \PeterSav\GModInterface\Repository\Store\RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$upgrade = $groupRepo->getByUpgradeId((int)$rankId);
		if (!$upgrade) {
			return $this->error('Invalid upgrade ID.');
		}

		$storeHelper = new \PeterSav\GModInterface\Helper\Store();
		$priceCalculation = $storeHelper->calculateUpgradePrice($recipientId, $rankId, $isGift);

		if (isset($priceCalculation['error'])) {
			return $this->error('Price calculation error: ' . $priceCalculation['error']);
		}

		$expectedPrice = $priceCalculation['price'];

    // Allow for small floating point differences (within 1 cent). We're non-profit so
    // if we lose a cent, so be it
		if (abs((float)$amount - $expectedPrice) > 0.01) {
			return $this->error('Payment amount mismatch: expected $' . number_format($expectedPrice, 2) . ' but received $' . number_format($amount, 2) . '. Please contact support.');
		}

		// We good
		/** @var \PeterSav\GModInterface\Service\Store\PurchaseProcessor $processor */
		$processor = $this->service('PeterSav\\GModInterface:Store\\PurchaseProcessor');
		[$ok, $data] = $processor->process([
			'paypal_order_id' => $paypalOrderId,
			'rank_id' => $rankId,
			'recipient_id' => $recipientId,
			'is_gift' => $isGift,
			'amount' => $amount,
			'currency' => $currency,
			'payer_id' => $payerId,
			'transaction_id' => $transactionId,
			'purchaser_user_id' => XF::visitor()->user_id,
		]);
		if (!$ok) {
			return $this->error($data);
		}

		$reply = $this->view('', 'gmod_store_processorder', [
			'paymentFound' => true,
			'orderId' => $paypalOrderId,
			'transactionId' => $transactionId,
			'isGift' => $isGift,
			'rankId' => $rankId,
			'recipientId' => $recipientId,
			'amount' => $amount,
			'currency' => $currency,
			'upgrade' => $upgrade,
			'dumpResponse' => $captureResult
		]);

		return $reply;
	}

	public function actionProcesspayment() {
		$this->assertPostOnly();

		$paypalOrderId = $this->filter('paypal_order_id', 'string');
		$rankId = $this->filter('rank_id', 'int');
		$recipientId = $this->filter('recipient_id', 'int');
		$isGift = $this->filter('is_gift', 'bool');
		$amount = $this->filter('amount', 'float');
		$currency = $this->filter('currency', 'string');
		$payerId = $this->filter('payer_id', 'string');
		$transactionId = $this->filter('transaction_id', 'string');

		if (empty($paypalOrderId) || empty($rankId) || empty($recipientId) || empty($transactionId)) {
			return $this->error("Missing required payment information.");
		}

		$paypalConfig = $this->getPayPalConfig();
		/** @var \PeterSav\GModInterface\Service\Store\PaypalVerifier $paypal */
		$paypal = $this->service('PeterSav\\GModInterface:Store\\PaypalVerifier', $paypalConfig);
		$token = $paypal->getAccessToken();

		if (isset($token['error']) || empty($token['access_token'])) {
			return $this->error('PayPal authentication failed');
		}

		$order = $paypal->fetchOrder($paypalOrderId, $token['access_token']);
		if (!isset($order['status']) || $order['status'] !== 'COMPLETED') {
			return $this->error('PayPal order verification failed: Order not completed');
		}

		/** @var RankGroup $groupRepo */
		$groupRepo = $this->repository('PeterSav\\GModInterface:Store\\RankGroup');
		$upgrade = $groupRepo->getByUpgradeId((int)$rankId);
		if (!$upgrade) {
			return $this->error('Invalid upgrade ID.');
		}

		// Calculate expected price based on recipient's rank
		$storeHelper = new \PeterSav\GModInterface\Helper\Store();
		$priceCalculation = $storeHelper->calculateUpgradePrice($recipientId, $rankId, false);

		if (isset($priceCalculation['error'])) {
			return $this->error('Price calculation error: ' . $priceCalculation['error']);
		}

		$expectedPrice = $priceCalculation['price'];

    // Allow for small floating point differences (within 1 cent). We're non-profit so
    // if we lose a cent, so be it
		if (abs($amount - $expectedPrice) > 0.01) {
			return $this->error('Payment amount ($' . number_format($amount, 2) . ') does not match expected price ($' . number_format($expectedPrice, 2) . ').');
		}

		/** @var \PeterSav\GModInterface\Service\Store\PurchaseProcessor $processor */
		$processor = $this->service('PeterSav\\GModInterface:Store\\PurchaseProcessor');
		[$ok, $data] = $processor->process([
			'paypal_order_id' => $paypalOrderId,
			'rank_id' => $rankId,
			'recipient_id' => $recipientId,
			'is_gift' => $isGift,
			'amount' => $amount,
			'currency' => $currency,
			'payer_id' => $payerId,
			'transaction_id' => $transactionId,
			'purchaser_user_id' => XF::visitor()->user_id,
		]);
		if (!$ok) {
			return $this->error($data);
		}

		$reply = $this->view('', 'gmod_store_processorder', [
			'paymentFound' => true,
			'orderId' => $paypalOrderId,
			'transactionId' => $transactionId,
			'isGift' => $isGift,
			'rankId' => $rankId,
			'recipientId' => $recipientId,
			'amount' => $amount,
			'currency' => $currency,
			'upgrade' => $upgrade,
			'dumpResponse' => $order
		]);

		return $reply;
	}

	public function actionUserselect() {
		return $this->view('', 'gmod_store_giftuser');
	}

	public function actionUserselectsearch(ParameterBag $params) {
		$this->assertPostOnly();

		if ($this->isPost()) {
			$db = XF::db();
			$user = $this->filter('username', 'str');
			$rankId = $this->filter('rank_id', 'int');

			if (empty($user) || empty($rankId)) {
				return $this->error("Username or Rank ID is not set in request! Please provide a username and rank ID." . $user . " | " . $rankId);
			}

			$selectedRankId = $db->fetchRow('SELECT * FROM gmod_store_rank_groups WHERE `upgrade_id` = ? ', $rankId);

			$username = $this->filter('username', 'str');
			/** @var \XF\Entity\User $user */
			$user = XF::app()->em()->findOne('XF:User', ['username' => $username]);

			if (!$user) {
				return $this->error(XF::phrase('requested_user_not_found'));
			} elseif ($user->user_id == XF::visitor()->user_id) {
				return $this->error("You can't gift a rank to yourself silly!");
			} elseif ($user->isMemberOf($selectedRankId["group_id"])) {
				return $this->error("The user you are trying to gift already has this exact rank!");
			} else {
				// Get recipient's highest rank priority and price for upgrade pricing
				$userHighestRankData = $db->fetchRow("
                    SELECT MIN(srg.rank_priority) as highest_priority, srg.price as current_rank_price
                    FROM gmod_store_rank_active sra
                    JOIN gmod_store_rank_groups srg ON sra.upgrade_group_id = srg.upgrade_id
                    WHERE sra.receiver_user_id = ?
                    AND (sra.expires = 0 OR sra.expires > ?)
                    ORDER BY srg.rank_priority DESC
                    LIMIT 1
                ", [$user->user_id, time()]);

				$userHighestRankPriority = $userHighestRankData ? (int)$userHighestRankData['highest_priority'] : 0;
				$userCurrentRankPrice = $userHighestRankData ? (float)$userHighestRankData['current_rank_price'] : 0;

				if ($userHighestRankPriority > 0 && $userHighestRankPriority > $selectedRankId["rank_priority"]) {
					return $this->error("The user you are trying to gift already has a higher tier rank!");
				}

				$reply = $this->view();
				$reply->setJsonParams([
					'message' => "Selected " . $user->username . " as gift recipient.",
					'user_id' => $user->user_id,
					'user_name' => $user->username,
					'highest_rank_priority' => $userHighestRankPriority,
					'current_rank_price' => $userCurrentRankPrice
				]);

				return $reply;
			}
		}

		return $this->error("Something went wrong while requesting the giftee's user account. Please try again.");
	}

	/**
	 * Get PayPal credentials and URLs based on production/sandbox setting
	 * @return array
	 */
	private function getPayPalConfig() {
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

	/**
	 * @param \XF\Entity\SessionActivity[] $activities
	 * @return bool|mixed
	 */
	public static function getActivityDetails(array $activities) {
		$details = array(
			array(
				"description" => "Viewing ",
				"title" => " the rank store",
				"url" => "/store"
			)
		);

		return $details;
	}
}
