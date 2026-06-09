<?php

namespace Shoutbox\Entity;

use XF;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property string|null $banned_on
 * @property string|null $expires_on
 * @property string $reason
 * @property int $ban_user_id
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read User|null $BannedBy
 */
class ShoutboxBan extends Entity
{
	protected function _preSave()
	{
		// Check if user is already banned
		$existingBan = $this->em()->findOne(ShoutboxBan::class, ['user_id' => $this->user_id]);
		if ($existingBan && $existingBan !== $this) {
			$this->error(XF::phrase('this_user_is_already_banned_from_shoutbox'));
		}
	}

	protected function _postSave()
	{
		parent::_postSave();

		if ($this->isInsert()) {
			$this->app()->logger()->logModeratorAction(
				'user',
				$this->User,
				'shoutbox_ban',
				['reason' => $this->reason]
			);
		}
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		/** @var User $visitor */
		$visitor = XF::visitor();

		/** @var ShoutboxBanHistory $history */
		$history = $this->em()->create('Shoutbox:ShoutboxBanHistory');
		$history->user_id = $this->user_id;
		$history->banned_on = $this->banned_on;
		$history->expires_on = $this->expires_on;
		$history->reason = $this->reason;
		$history->ban_user_id = $this->ban_user_id;
		$history->unbanned_on = date('Y-m-d H:i:s');
		$history->unban_user_id = $visitor->user_id;
		$history->save();

		$this->app()->logger()->logModeratorAction(
			'user',
			$this->User,
			'shoutbox_unban',
			[]
		);
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'shoutbox_ban';
		$structure->shortName = 'Shoutbox:ShoutboxBan';
		$structure->primaryKey = 'user_id';
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'banned_on' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'expires_on' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'reason' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'ban_user_id' => ['type' => self::UINT, 'required' => true],
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
		];

		return $structure;
	}
}
