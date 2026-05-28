<?php

namespace PeterSav\GModInterface\Repository\Store;

use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

class Transaction extends Repository {
	public function __construct(Manager $em, $identifier) {
		$em = clone $em;

		parent::__construct($em, $identifier);
	}

	public function isDuplicate(string $externalTxnId): bool {
		// Use finder to avoid hard-coded table names
		$finder = $this->finder('PeterSav\\GModInterface:Store\\Transaction');
		$finder->where('transaction_log', 'LIKE', '%"transaction_id":"' . $this->db()->escapeLike($externalTxnId) . '"%');

		return (bool)$finder->total();
	}

	public function log(array $payload, int $purchaserUserId = 0, int $receiverUserId = 0) {
		/** @var \PeterSav\GModInterface\Entity\Store\Transaction $txn */
		$txn = $this->em->create('PeterSav\\GModInterface:Store\\Transaction');
		$txn->transaction_id = (string)($payload['paypal_order_id'] ?? '');
		$txn->purchaser_user_id = $purchaserUserId;
		$txn->receiver_user_id = $receiverUserId;

		$originalTime = !empty($payload['transaction_time']) ? (int)$payload['transaction_time'] : 0;
		$txn->transaction_time = $originalTime > 0 ? $originalTime : time();

		if ($originalTime > 0) {
			$payload['original_purchase_at'] = date('c', $originalTime);
			$payload['recovered_at'] = date('c');
		} else {
			$payload['processed_at'] = date('c');
		}
		$txn->transaction_log = json_encode($payload);
		$txn->save();

		return $txn;
	}
}
