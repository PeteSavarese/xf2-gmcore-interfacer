<?php

namespace PeterSav\GModInterface\Service\Store;

use XF\Service\AbstractService;
use PeterSav\GModInterface\Repository\Store\Transaction as TxRepo;
use PeterSav\GModInterface\Repository\Store\RankGroup as GroupRepo;

class PurchaseProcessor extends AbstractService {
	protected TxRepo $txRepo;
	protected GroupRepo $groupRepo;

	public function __construct(\XF\App $app) {
		parent::__construct($app);
		$this->txRepo = $app->repository('PeterSav\\GModInterface:Store\\Transaction');
		$this->groupRepo = $app->repository('PeterSav\\GModInterface:Store\\RankGroup');
	}

	/**
	 * @return array [bool success, mixed dataOrError]
	 */
	public function process(array $input): array {
		$group = $this->groupRepo->getByUpgradeId((int)$input['rank_id']);
		if (!$group) {
			return [false, 'Invalid upgrade ID.'];
		}

		$storeHelper = new \PeterSav\GModInterface\Helper\Store();
		$priceCalculation = $storeHelper->calculateUpgradePrice(
			(int)$input['recipient_id'],
			(int)$input['rank_id'],
			$input['is_gift'] ?? false
		);

		if (isset($priceCalculation['error'])) {
			return [false, 'Price calculation error: ' . $priceCalculation['error']];
		}

		$expected = $priceCalculation['price'];
		$isUpgrade = $priceCalculation['is_upgrade'] ?? false;

		$skipAmountCheck = !empty($input['skip_amount_check']);
		if (!$skipAmountCheck && abs(((float)$input['amount']) - $expected) > 0.01) {
			$errorDetails = sprintf(
				'Payment amount mismatch - Purchaser: %d, Receiver: %d, Rank ID: %d, Expected: $%.2f (Base: $%.2f, IsUpgrade: %s), Received: $%.2f, PayPal Order: %s',
				(int)$input['purchaser_user_id'],
				(int)$input['recipient_id'],
				(int)$input['rank_id'],
				$expected,
				(float)$group->price,
				$isUpgrade ? 'Yes' : 'No',
				(float)$input['amount'],
				(string)$input['paypal_order_id']
			);
			\XF::logError($errorDetails);

			return [false, sprintf(
				'Payment amount mismatch: expected $%.2f but received $%.2f. Please contact an Owner or Lead Administrator.',
				$expected,
				(float)$input['amount']
			)];
		}

		if (!empty($input['transaction_id']) && $this->txRepo->isDuplicate((string)$input['transaction_id'])) {
			return [false, 'This transaction has already been processed.'];
		}

		// Log transaction
		$this->txRepo->log([
			'paypal_order_id' => (string)$input['paypal_order_id'],
			'rank_id' => (int)$input['rank_id'],
			'recipient_id' => (int)$input['recipient_id'],
			'is_gift' => (bool)$input['is_gift'],
			'amount' => (float)$input['amount'],
			'currency' => (string)$input['currency'],
			'payer_id' => (string)$input['payer_id'],
			'transaction_id' => (string)$input['transaction_id'],
			'transaction_time' => (int)($input['transaction_time'] ?? 0),
		], (int)$input['purchaser_user_id'], (int)$input['recipient_id']);

		// Grant rank via existing helper (reuse instance from price calculation)
		$storeHelper->processPlayerStoreRankComplete(
			(int)$input['purchaser_user_id'],
			(int)$input['recipient_id'],
			(int)$input['rank_id'],
			(string)$input['paypal_order_id']
		);

		return [true, $group];
	}
}
