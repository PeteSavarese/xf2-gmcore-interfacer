<?php

namespace PeterSav\GModInterface\Entity;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

/**
 * @property int $mapping_id
 * @property string $discord_role_id
 * @property int $forum_group_id
 * @property int|null $created_by_forum_id
 * @property string $created_at
 * @property \XF\Entity\UserGroup $UserGroup
 * @property \XF\Entity\User|null $CreatedByUser
 */
class DiscordRoleForumGroup extends Entity {
	public function __construct(Manager $em, Structure $structure, array $values = [], array $relations = []) {
		$em = clone $em;
		$reflection = new \ReflectionObject($em);
		$dbProperty = $reflection->getProperty('db');
		$dbProperty->setAccessible(true);
		$dbProperty->setValue($em, AbstractController::getCoreDbInstance());
		parent::__construct($em, $structure, $values, $relations);
	}

	public function getDiscordRoleData(): ?array {
		if (!$this->discord_role_id) {
			return null;
		}

		$discordApi = \XF::app()->service(\PeterSav\GModInterface\Service\DiscordAPI::class);
		return $discordApi->getGuildRole($this->discord_role_id);
	}

	public function isValid(): bool {
		if (!$this->UserGroup) {
			return false;
		}

		$roleData = $this->getDiscordRoleData();
		return $roleData !== null && !isset($roleData['code']);
	}

	public static function getStructure(Structure $structure): Structure {
		$structure->table = 'discord_role_forum_group';
		$structure->shortName = 'PeterSav\GModInterface:DiscordRoleForumGroup';
		$structure->primaryKey = 'mapping_id';
		$structure->columns = [
			'mapping_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'discord_role_id' => ['type' => self::STR, 'maxLength' => 20, 'required' => true],
			'forum_group_id' => ['type' => self::UINT, 'required' => true],
			'created_by_forum_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'created_at' => ['type' => self::STR, 'default' => ''] // Let default val for col in DB handle setting CURRENT_TIMESTAMP
		];

		$structure->getters = [];

		$structure->relations = [
			'UserGroup' => [
				'entity' => 'XF:UserGroup',
				'type' => self::TO_ONE,
				'conditions' => 'forum_group_id',
				'primary' => true
			],
			'CreatedByUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'created_by_forum_id',
				'primary' => false
			]
		];

		$structure->indexes = [
			'discord_role_id' => ['discord_role_id'],
			'forum_group_id' => ['forum_group_id'],
			'uq_role_group' => ['discord_role_id', 'forum_group_id', 'unique' => true]
		];

		return $structure;
	}

	protected function _preSave() {
		if ($this->isInsert()) {
			$existing = $this->em()->findOne('PeterSav\GModInterface:DiscordRoleForumGroup', [
				'discord_role_id' => $this->discord_role_id,
				'forum_group_id' => $this->forum_group_id
			]);

			if ($existing) {
				$this->error('This Discord role is already mapped to this forum group.', 'discord_role_id');
			}

			$userGroup = \XF::em()->find('XF:UserGroup', $this->forum_group_id);
			if (!$userGroup) {
				$this->error('Invalid forum group specified.', 'forum_group_id');
			}

			if (!$this->created_at) {
				$this->created_at = date('Y-m-d H:i:s', \XF::$time);
			}
		}
	}

	protected function _postSave() {
		\PeterSav\GModInterface\Service\DiscordSync::clearMappingCache();
	}

	protected function _postDelete() {
		\PeterSav\GModInterface\Service\DiscordSync::clearMappingCache();
	}
}