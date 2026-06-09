<?php

namespace PeterSav\GModInterface\Api\Controller;

use PeterSav\GModInterface\Repository\DiscordLink as DiscordLinkRepository;
use PeterSav\GModInterface\Service\DiscordSync;
use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;

class Discord extends AbstractController {
	public function actionGet(ParameterBag $params): ApiResult|Error {
		$this->assertApiScopeByRequestMethod('discord');

		$discordId = $params->get('discord_id');

		if (!$discordId) {
			return $this->apiError(\XF::phrase('required_field_missing', ['field' => 'discord_id']), 'discord_id_required');
		}

		/** @var DiscordLinkRepository $discordLinkRepo */
		$discordLinkRepo = $this->repository('PeterSav\GModInterface:DiscordLink');

		$activeLink = $discordLinkRepo->findActiveByDiscordId($discordId);

		if (!$activeLink) {
			return $this->apiError('No active forum link found for this Discord ID', 'discord_link_not_found', [], 404);
		}

		/** @var \XF\Entity\User $user */
		$user = $activeLink->forum_id ? \XF::em()->find('XF:User', $activeLink->forum_id) : null;

		if (!$user) {
			return $this->apiError('Associated forum user not found', 'forum_user_not_found', [], 404);
		}

		$result = $user->toApiResult();
		$result->includeExtra('is_banned', (bool)$user->is_banned);
		$result->includeExtra('discord_id', $activeLink->discord_id);
		$result->includeExtra('discord_link_created_at', $activeLink->created_at);
		$result->includeExtra('steam_id', $this->getUserSteamId($user));

		return $this->apiSuccess(['data' => $result]);
	}

	public function actionPostSync(ParameterBag $params): ApiResult|Error {
		$this->assertApiScopeByRequestMethod('discord');

		$discordId = $params->get('discord_id');

		if (!$discordId) {
			return $this->apiError(\XF::phrase('required_field_missing', ['field' => 'discord_id']), 'discord_id_required');
		}

		/** @var DiscordLinkRepository $discordLinkRepo */
		$discordLinkRepo = $this->repository('PeterSav\GModInterface:DiscordLink');

		$activeLink = $discordLinkRepo->findActiveByDiscordId($discordId);

		if (!$activeLink) {
			return $this->apiError('No active forum link found for this Discord ID', 'discord_link_not_found', [], 404);
		}

		/** @var \XF\Entity\User $user */
		$user = $activeLink->forum_id ? \XF::em()->find('XF:User', $activeLink->forum_id) : null;

		if (!$user) {
			return $this->apiError('Associated forum user not found', 'forum_user_not_found', [], 404);
		}

		/** @var DiscordSync $discordSync */
		$discordSync = $this->app()->service(DiscordSync::class);

		$result = $discordSync->sync($user);

		if ($result['success']) {
			unset($result['success'], $result['discord_id']);
			return $this->apiSuccess([
				'data' => $result,
			]);
		} else {
			return $this->apiError($result['message'], 'sync_failed', [], 400);
		}
	}

	public function actionPostRemoveRoles(ParameterBag $params): ApiResult|Error {
		$this->assertApiScopeByRequestMethod('discord');

		$discordId = $params->get('discord_id');
		$reason = $this->filter('reason', 'str');

		if (!$discordId) {
			return $this->apiError(\XF::phrase('required_field_missing', ['field' => 'discord_id']), 'discord_id_required');
		}

		/** @var DiscordSync $discordSync */
		$discordSync = $this->app()->service(DiscordSync::class);

		$auditReason = $reason ?: "API request: Remove all forum-managed roles";
		$result = $discordSync->removeAllManagedRoles($discordId, $auditReason);

		if ($result['success']) {
			unset($result['success'], $result['discord_id']);
			return $this->apiSuccess(['data' => $result]);
		} else {
			return $this->apiError($result['message'], 'remove_roles_failed', [], 400);
		}
	}

	protected function getUserSteamId(\XF\Entity\User $user): ?string {
		$connectedAccount = $this->finder('XF:UserConnectedAccount')
			->where('user_id', $user->user_id)
			->where('provider', 'steam')
			->fetchOne();

		if ($connectedAccount && !empty($connectedAccount->provider_key)) {
			return $connectedAccount->provider_key;
		}

		return null;
	}
}
