<?php

namespace PeterSav\GModInterface\Listener;

use PeterSav\GModInterface\Service\DiscordSync as DiscordSyncService;

use XF;

class DiscordSync {
	protected static function discordSync(): DiscordSyncService {
		return XF::app()->service(DiscordSyncService::class);
	}

	/**
	 * Event: entity_post_save
	 * Hint: XF\Entity\User
	 * Callback: PeterSav\GModInterface\Listener\DiscordSync::userEntityPostSaveD
	 * Description: Sync Discord roles when a user's primary or secondary groups change.
	 */
	public static function userEntityPostSave(\XF\Mvc\Entity\Entity $entity) {
		// Check if the user's primary or secondary groups have changed.
		if ($entity->isChanged('user_group_id') || $entity->isChanged('secondary_group_ids')) {
			/** @var DiscordSyncService $syncService */
			self::discordSync()->sync($entity);
		}
	}

	/**
	 * Event: entity_post_save
	 * Hint: XF\Entity\UserBan
	 * Callback: PeterSav\GModInterface\Listener\DiscordSync::userBanEntityPostSave
	 * Description: Sync Discord roles when a user is banned or unbanned.
	 */
	public static function userBanEntityPostSave(\XF\Mvc\Entity\Entity $entity) {
		/** @var \XF\Entity\UserBan $entity */
		if ($entity->User) {
			/** @var \PeterSav\GModInterface\Repository\DiscordLink */
			$discordLinkRepo = XF::repository('PeterSav\GModInterface:DiscordLink');
			$discordLinkRepo->findActiveByForumId($entity->User->user_id);
			self::discordSync()->removeAllManagedRoles($entity->User->user_id);
		}
	}

	/**
	 * Event: entity_post_delete
	 * Hint: XF\Entity\UserBan
	 * Callback: PeterSav\GModInterface\Listener\DiscordSync::userBanEntityPostDelete
	 * Description: Sync Discord roles when a user ban is lifted (deleted).
	 */
	public static function userBanEntityPostDelete(\XF\Mvc\Entity\Entity $entity) {
		/** @var \XF\Entity\UserBan $entity */
		if ($entity->User) {
			self::discordSync()->sync($entity->User);
		}
	}
}