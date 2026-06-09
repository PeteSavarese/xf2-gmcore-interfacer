<?php

namespace Shoutbox\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $ban_history_id
 * @property int $user_id
 * @property string|null $banned_on
 * @property string|null $expires_on
 * @property string $reason
 * @property int $ban_user_id
 * @property string|null $unbanned_on
 * @property int $unban_user_id
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read User|null $BannedBy
 * @property-read User|null $UnbannedBy
 */
class ShoutboxBanHistory extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'shoutbox_ban_history';
		$structure->shortName = 'Shoutbox:ShoutboxBanHistory';
		$structure->primaryKey = 'ban_history_id';
		$structure->columns = [
			'ban_history_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'banned_on' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'expires_on' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'reason' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'ban_user_id' => ['type' => self::UINT, 'default' => 0],
			'unbanned_on' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'unban_user_id' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'BannedBy' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$ban_user_id']],
				'primary' => true,
			],
			'UnbannedBy' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$unban_user_id']],
				'primary' => true,
			],
		];

		return $structure;
	}
}
