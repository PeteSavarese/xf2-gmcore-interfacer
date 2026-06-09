<?php

namespace PeterSav\GModInterface\Service;

use PeterSav\GModInterface\Helper\CoreDatabase;
use PeterSav\GModInterface\Repository\DiscordLink as DiscordLinkRepository;
use XF;

class DiscordSync {
	protected DiscordAPI $discord;
	protected DiscordLinkRepository $discordLinkRepo;

	public function __construct() {
		$this->discord = XF::app()->service(DiscordAPI::class);
		$this->discordLinkRepo = XF::repository('PeterSav\GModInterface:DiscordLink');
	}

	/**
	 * Get the current role mappings from the database
	 * @return array Array of forum_group_id => discord_role_id
	 */
	protected function getRoleMappings(): array {
		$cacheKey = 'discord_role_mappings';
		$simpleCache = XF::app()->simpleCache();
		$mappings = $simpleCache->getValue('PeterSav/GModInterface', $cacheKey);

		if ($mappings === null) {
			$mappings = [];

			/** @var \PeterSav\GModInterface\Entity\DiscordRoleForumGroup[] $roleMappings */
			$roleMappings = CoreDatabase::getCoreEntityManager()->getFinder('PeterSav\GModInterface:DiscordRoleForumGroup')->fetch();

			foreach ($roleMappings as $mapping) {
				$mappings[$mapping->forum_group_id] = $mapping->discord_role_id;
			}

			// Cache the mappings
			$simpleCache->setValue('PeterSav/GModInterface', $cacheKey, $mappings);
		}

		return $mappings;
	}

	/**
	 * Get all managed Discord role IDs
	 * @return array
	 */
	protected function getAllManagedRoleIds(): array {
		return array_values($this->getRoleMappings());
	}

	/**
	 * Clear cached role mappings (call this when mappings are updated)
	 */
	public static function clearMappingCache(): void {
		XF::app()->simpleCache()->setValue('PeterSav/GModInterface', 'discord_role_mappings', null);
	}

	/**
	 * Sync a user's Discord roles based on their forum user groups
	 * @return array{success: bool, message: string, roles_added: string[], roles_removed: string[], discord_id: string|null}
	 */
	public function sync(\XF\Entity\User $user): array {
		$result = [
			'success' => false,
			'message' => '',
			'roles_added' => [],
			'roles_removed' => [],
			'discord_id' => null
		];

		$activeLink = $this->discordLinkRepo->findActiveByForumId($user->user_id);
		if (!$activeLink) {
			$result['message'] = 'No active Discord link found for user';
			return $result;
		}

		$result['discord_id'] = $activeLink->discord_id;

		$discordMember = $this->discord->getGuildMember($activeLink->discord_id);

		if (!$discordMember) {
			$result['message'] = 'Could not retrieve Discord member information';
			return $result;
		}

		$currentDiscordRoles = $discordMember['roles'] ?? [];

		$roleMappings = $this->getRoleMappings();
		$expectedRoleIds = [];
		$userGroupIds = [$user->user_group_id, ...$user->secondary_group_ids];

		foreach ($userGroupIds as $groupId) {
			if (isset($roleMappings[$groupId])) {
				$expectedRoleIds[] = $roleMappings[$groupId];
			}
		}

		$rolesToAdd = array_diff($expectedRoleIds, $currentDiscordRoles);
		$managedRoles = $this->getAllManagedRoleIds();
		$rolesToRemove = array_intersect($currentDiscordRoles, array_diff($managedRoles, $expectedRoleIds));

		foreach ($rolesToAdd as $roleId) {
			$addResult = $this->discord->addMemberRole(
				$activeLink->discord_id,
				$roleId,
				"XenForo sync: User {$user->username} ({$user->user_id}) gained forum group"
			);

			if ($addResult['success']) {
				$result['roles_added'][] = $roleId;
			} else {
				XF::logError("Failed to add Discord role {$roleId} to user {$user->user_id} (HTTP {$addResult['status_code']}): " . json_encode($addResult['data']));
			}
		}

		foreach ($rolesToRemove as $roleId) {
			$removeResult = $this->discord->removeMemberRole(
				$activeLink->discord_id,
				$roleId,
				"XenForo sync: User {$user->username} ({$user->user_id}) lost forum group"
			);

			if ($removeResult['success']) {
				$result['roles_removed'][] = $roleId;
			} else {
				XF::logError("Failed to remove Discord role {$roleId} from user {$user->user_id} (HTTP {$removeResult['status_code']}): " . json_encode($removeResult['data']));
			}
		}

		$result['success'] = true;
		$result['message'] = sprintf(
			'Sync completed. Added %d roles, removed %d roles.',
			count($result['roles_added']),
			count($result['roles_removed'])
		);

		return $result;
	}

	/**
	 * Remove all forum-managed roles from a Discord user
	 * @return array{success: bool, message: string, roles_removed: string[], discord_id: string}
	 */
	public function removeAllManagedRoles(string $discordId, ?string $reason = null): array {
		$result = [
			'success' => false,
			'message' => '',
			'roles_removed' => [],
			'discord_id' => $discordId
		];

		$discordMember = $this->discord->getGuildMember($discordId);
		if (!$discordMember) {
			$result['message'] = 'Could not retrieve Discord member information';
			return $result;
		}

		$currentDiscordRoles = $discordMember['roles'] ?? [];
		$managedRoles = $this->getAllManagedRoleIds();

		$rolesToRemove = array_intersect($currentDiscordRoles, $managedRoles);

		if (empty($rolesToRemove)) {
			$result['success'] = true;
			$result['message'] = 'No managed roles to remove';
			return $result;
		}

		$auditReason = $reason ?: "XenForo: Removing all forum-managed roles";

		foreach ($rolesToRemove as $roleId) {
			$removeResult = $this->discord->removeMemberRole($discordId, $roleId, $auditReason);

			if ($removeResult['success']) {
				$result['roles_removed'][] = $roleId;
			} else {
				XF::logError("Failed to remove Discord role {$roleId} from Discord user {$discordId} (HTTP {$removeResult['status_code']}): " . json_encode($removeResult['data']));
			}
		}

		$result['success'] = true;
		$result['message'] = sprintf(
			'Removed %d managed roles from Discord user.',
			count($result['roles_removed'])
		);

		return $result;
	}
}
