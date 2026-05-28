<?php

namespace PeterSav\GModInterface\Entity\Store;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Transaction extends Entity {
	public static function getStructure(Structure $structure) {
		$structure->table = 'gmod_store_rank_transactions';
		$structure->shortName = 'PeterSav\\GModInterface:Store\\Transaction';
		$structure->primaryKey = 'transaction_id';
		$structure->columns = [
			'transaction_id' => ['type' => self::STR, 'maxLength' => 20, 'required' => true],
			'purchaser_user_id' => ['type' => self::UINT, 'nullable' => true],
			'receiver_user_id' => ['type' => self::UINT, 'nullable' => true],
			'transaction_time' => ['type' => self::UINT, 'default' => 0],
			'transaction_log' => ['type' => self::STR, 'default' => ''],
		];

		return $structure;
	}
}
