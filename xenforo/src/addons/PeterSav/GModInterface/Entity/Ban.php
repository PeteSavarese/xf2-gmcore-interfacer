<?php

namespace PeterSav\GModInterface\Entity;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

/**
 * @property int|null $id
 * @property string $steamid
 * @property string $name
 * @property string|null $reason
 * @property int $banned_on
 * @property int $unban_time
 * @property string|null $banned_by
 * @property string|null $banned_by_steamid
 * @property string|null $server
 * @property int $status
 * @property string|null $void
 * @property string|null $void_user
 * @property string|null $void_reason
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $unbanned_at
 * @property string|null $active_ban_key
 */
class Ban extends Entity {
	public function __construct(Manager $em, Structure $structure, array $values = [], array $relations = []) {
		$em = clone $em;
		$reflection = new \ReflectionObject($em);
		$dbProperty = $reflection->getProperty('db');
		$dbProperty->setAccessible(true);
		$dbProperty->setValue($em, AbstractController::getCoreDbInstance());
		parent::__construct($em, $structure, $values, $relations);
	}

	public static function getStructure(Structure $structure): Structure {
		$structure->table = 'bans';
		$structure->shortName = 'PeterSav\GModInterface:Ban';
		$structure->primaryKey = 'id';
		$structure->columns = [
			'id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'steamid' => ['type' => self::STR, 'maxLength' => 32, 'required' => true],
			'name' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'reason' => ['type' => self::STR, 'default' => null, 'nullable' => true],
			'banned_on' => ['type' => self::UINT, 'required' => true],
			'unban_time' => ['type' => self::UINT, 'default' => 0],
			'banned_by' => ['type' => self::STR, 'maxLength' => 100, 'default' => null, 'nullable' => true],
			'banned_by_steamid' => ['type' => self::STR, 'maxLength' => 32, 'default' => null, 'nullable' => true],
			'server' => ['type' => self::STR, 'maxLength' => 100, 'default' => null, 'nullable' => true],
			'status' => ['type' => self::BOOL, 'default' => true],
			'void' => ['type' => self::STR, 'maxLength' => 10, 'default' => null, 'nullable' => true],
			'void_user' => ['type' => self::STR, 'maxLength' => 100, 'default' => null, 'nullable' => true],
			'void_reason' => ['type' => self::STR, 'maxLength' => 255, 'default' => null, 'nullable' => true],
			'created_at' => ['type' => self::STR, 'default' => 'CURRENT_TIMESTAMP'],
			'updated_at' => ['type' => self::STR, 'default' => 'CURRENT_TIMESTAMP'],
			'unbanned_at' => ['type' => self::STR, 'default' => null, 'nullable' => true],
			'active_ban_key' => ['type' => self::STR, 'maxLength' => 255, 'default' => null, 'nullable' => true],
		];

		$structure->getters = [];

		$structure->relations = [

		];

		$structure->indexes = [

		];

		return $structure;
	}

	protected function _preInsert() {
		if (!$this->banned_on) {
			$this->banned_on = \XF::$time;
		}
	}
}