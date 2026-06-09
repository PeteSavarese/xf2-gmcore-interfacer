<?php

namespace PeterSav\GModInterface\Entity;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

/**
 * @property int $link_id
 * @property int $forum_id
 * @property string $discord_id
 * @property int|null $unlinked_by_forum_id
 * @property int $created_at
 * @property \DateTime|null $unlinked_at
 * @property int|null $active_forum_id
 * @property string|null $active_discord_id
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User|null $UnlinkedByUser
 */
class DiscordLink extends Entity {
	public function __construct(Manager $em, Structure $structure, array $values = [], array $relations = []) {
		$em = clone $em;
		$reflection = new \ReflectionObject($em);
		$dbProperty = $reflection->getProperty('db');
		$dbProperty->setAccessible(true);
		$dbProperty->setValue($em, AbstractController::getCoreDbInstance());
		parent::__construct($em, $structure, $values, $relations);
	}

	public function isActive(): bool {
		return $this->unlinked_at === null;
	}

	public function unlink(?int $unlinkedByUserId = null): bool {
		if (!$this->isActive()) {
			return false;
		}

		$this->unlinked_at = date('Y-m-d H:i:s', \XF::$time);
		if ($unlinkedByUserId) {
			$this->unlinked_by_forum_id = $unlinkedByUserId;
		}

		return $this->save();
	}

	/**
	 * Get the Discord user information via API (if needed)
	 */
	public function getDiscordUserData(): ?array {
		if (!$this->isActive() || !$this->discord_id) {
			return null;
		}

		$discordApi = \XF::app()->service(\PeterSav\GModInterface\Service\DiscordAPI::class);
		return $discordApi->getGuildMember($this->discord_id);
	}

	public static function getStructure(Structure $structure): Structure {
		$structure->table = 'discord_link';
		$structure->shortName = 'PeterSav\GModInterface:DiscordLink';
		$structure->primaryKey = 'link_id';
		$structure->columns = [
			'link_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'forum_id' => ['type' => self::UINT, 'required' => true],
			'discord_id' => ['type' => self::STR, 'maxLength' => 20, 'required' => true],
			'unlinked_by_forum_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'created_at' => ['type' => self::STR, 'default' => 'CURRENT_TIMESTAMP'],
			'unlinked_at' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'active_forum_id' => ['type' => self::UINT, 'nullable' => true, 'computed' => true],
			'active_discord_id' => ['type' => self::STR, 'maxLength' => 20, 'nullable' => true, 'computed' => true]
		];

		$structure->getters = [];

		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'forum_id',
				'primary' => true
			],
			'UnlinkedByUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'unlinked_by_forum_id',
				'primary' => false
			]
		];

		$structure->indexes = [
			'active_forum_id' => ['active_forum_id', 'unique' => true],
			'active_discord_id' => ['active_discord_id', 'unique' => true],
			'forum_id_active' => ['forum_id', 'unlinked_at'],
			'discord_id_active' => ['discord_id', 'unlinked_at']
		];

		return $structure;
	}

	protected function _preSave() {
		if ($this->isInsert() && $this->isActive()) {
			/** @var \PeterSav\GModInterface\Repository\DiscordLink */
			$repo = $this->repository('PeterSav\GModInterface:DiscordLink');

			$existingForumLink = $repo->findActiveByForumId($this->forum_id);
			if ($existingForumLink) {
				$this->error('You already have an active Discord link. Please unlink it first.', 'forum_id');
			}

			$existingDiscordLink = $repo->findActiveByDiscordId($this->discord_id);
			if ($existingDiscordLink) {
				$this->error('That Discord account is already linked to a different forum account.', 'discord_id');
			}
		}

		if ($this->created_at === null) {
			$this->created_at = \XF::$time;
		}
	}
}