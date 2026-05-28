<?php

namespace PeterSav\GModInterface\Repository;

use PeterSav\GModInterface\Entity\DiscordLink as DiscordLinkEntity;
use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

class DiscordLink extends Repository {
	public function __construct(Manager $em, $identifier) {
		$em = clone $em;
		$reflection = new \ReflectionObject($em);
		$dbProperty = $reflection->getProperty('db');
		$dbProperty->setAccessible(true);
		$dbProperty->setValue($em, AbstractController::getCoreDbInstance());
		parent::__construct($em, $identifier);
	}

	public function findActiveByForumId(int $forumId): ?DiscordLinkEntity {
		return $this->finder('PeterSav\GModInterface:DiscordLink')
			->where('forum_id', $forumId)
			->where('unlinked_at', null)
			->fetchOne();
	}

	public function findActiveByDiscordId(string $discordId): ?DiscordLinkEntity {
		return $this->finder('PeterSav\GModInterface:DiscordLink')
			->where('discord_id', $discordId)
			->where('unlinked_at', null)
			->fetchOne();
	}

	public function createLink(int $forumId, string $discordId): DiscordLinkEntity {
		$link = $this->em->create('PeterSav\GModInterface:DiscordLink');
		$link->forum_id = $forumId;
		$link->discord_id = $discordId;
		$link->created_at = date('Y-m-d H:i:s', XF::$time);

		return $link;
	}

	public function unlinkByForumId(int $forumId, ?int $unlinkedByUserId = null): bool {
		$link = $this->findActiveByForumId($forumId);
		if (!$link) {
			return false;
		}

		return $link->unlink($unlinkedByUserId);
	}

	public function unlinkByDiscordId(string $discordId, ?int $unlinkedByUserId = null): bool {
		$link = $this->findActiveByDiscordId($discordId);
		if (!$link) {
			return false;
		}

		return $link->unlink($unlinkedByUserId);
	}
}