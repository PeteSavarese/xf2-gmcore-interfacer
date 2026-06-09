<?php

namespace PeterSav\GModInterface\Entity\Store;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class RankGroup extends Entity {
	public static function getStructure(Structure $structure) {
		$structure->table = 'gmod_store_rank_groups';
		$structure->shortName = 'PeterSav\\GModInterface:Store\\RankGroup';
		$structure->primaryKey = 'upgrade_id';
		$structure->columns = [
			'upgrade_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'title' => ['type' => self::STR, 'maxLength' => 35, 'required' => true],
			'description' => ['type' => self::STR, 'default' => ''],
			'rank_image' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'rank_priority' => ['type' => self::UINT, 'default' => 0],
			'group_id' => ['type' => self::UINT, 'required' => true],
			'price' => ['type' => self::FLOAT, 'required' => true],
			'length' => ['type' => self::UINT, 'default' => 0],
			'length_unit' => ['type' => self::STR, 'allowedValues' => ['day', 'month', 'year', ''], 'default' => ''],
			'can_purchase' => ['type' => self::UINT, 'default' => 1],
		];

		return $structure;
	}
}
