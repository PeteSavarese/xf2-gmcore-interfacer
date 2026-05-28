<?php

namespace Shoutbox\Api\Controller;

use PeterSav\GModInterface\Repository\DiscordLink;
use Shoutbox\Entity\Message;
use Shoutbox\Entity\ShoutboxBan;
use Shoutbox\Repository\Message as MessageRepo;
use Shoutbox\Service\DiscordEmoji\Transformer as DiscordEmojiTransformer;
use Throwable;
use XF;
use XF\Api\Controller\AbstractController;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;

/**
 * API controller for Discord bot integration.
 *
 * Provides endpoints for:
 * - Ingesting messages from Discord into XenForo shoutbox
 * - Deleting shoutbox messages when deleted from Discord
 *
 * Handles user linking via GMCore add-on:
 * - Linked Discord users post as their XenForo account
 * - Unlinked users create generic entries with Discord identity stored
 */
class Discord extends AbstractController
{
	/**
	 * Attempt to find a linked XenForo user ID for a Discord user.
	 *
	 * Uses the GMCore add-on to look up active Discord↔Forum account links.
	 * Returns null if:
	 * - Discord ID is empty
	 * - GMCore database is not configured
	 * - No active link exists
	 * - Lookup fails for any reason
	 *
	 * @param string $discordUserId Discord user snowflake ID
	 * @return int|null XenForo user ID if linked, null otherwise
	 */
	protected function tryGetLinkedForumUserId(string $discordUserId): ?int
	{
		$discordUserId = $this->sanitizeInboundText($discordUserId);
		if ($discordUserId === '') {
			return null;
		}
		$discordUserId = substr($discordUserId, 0, 20);

		try {
			// GMCore owns Discord Forum linking.
			$options = XF::options();
			$host = $options->gmod_interfacer_db_host ?? null;
			$dbName = $options->gmod_interfacer_db_name ?? null;

			if (!$host || !$dbName) {
				return null;
			}

			/** @var DiscordLink $repo */
			$repo = XF::repository('PeterSav\GModInterface:DiscordLink');
			$link = $repo->findActiveByDiscordId($discordUserId);
			if (!$link) {
				return null;
			}

			return $link->forum_id;
		} catch (Throwable $e) {
			// If core DB connection isn't configured, treat as unlinked.
			return null;
		}
	}

	/**
	 * Check if a message contains Discord mentions or pings.
	 *
	 * Prevents typical GMod behavior of Discord users from
   * abusing @everyone, @here, and other mention types in messages forwarded
   * from Discord to the shoutbox.
	 *
	 * Patterns checked:
	 * - @everyone and @here (text and escaped)
	 * - User mentions: <@123456> or <@!123456>
	 * - Role mentions: <@&123456>
	 * - Channel mentions: <#123456>
	 *
	 * @param string $message Message text to check
	 * @return bool True if Discord pings are detected
	 */
	protected function containsDiscordPings(string $message): bool
	{
		$patterns = [
			'/@everyone/',
			'/@here/',
			'/<@!??\d+>/',
			'/<@&\d+>/',
			'/<#\d+>/',
			'/<!@everyone>/',
			'/<!@here>/',
			'/<!@\d+>/',
			'/<!@&\d+>/',
			'/<!#\d+>/',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $message)) {
				return true;
			}
		}

		$lower = strtolower($message);
		return (strpos($lower, '@everyone') !== false || strpos($lower, '@here') !== false);
	}

	/**
	 * Sanitize text received from Discord API.
	 *
	 * @param string $text Raw text from Discord
	 * @return string Sanitized text safe for storage and display
	 */
	protected function sanitizeInboundText(string $text): string
	{
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
		$text = trim($text);

		return $text;
	}

	protected function emojiTransformer(): DiscordEmojiTransformer
	{
		/** @var DiscordEmojiTransformer $svc */
		$svc = $this->service('Shoutbox:DiscordEmoji\\Transformer');

		return $svc;
	}

	/**
	 * Ingest a message from Discord into the XenForo shoutbox.
	 *
	 * POST /api/shoutbox/discord/ingest
	 *
	 * @return ApiResult|Error JSON response with message_id or error details
	 */
	public function actionPostIngest(ParameterBag $params): ApiResult|Error
	{
		$this->assertApiScopeByRequestMethod('shoutbox');

		$message = $this->filter('message', 'str');
		$discordMessageId = $this->filter('discord_message_id', 'str');
		$discordUserId = $this->filter('discord_user_id', 'str');
		$discordUsername = $this->filter('discord_username', 'str');
		$discordAvatarUrl = $this->filter('discord_avatar_url', 'str');
		$messageUrl = $this->filter('message_url', 'str');

		$message = $this->sanitizeInboundText($message);
		$discordMessageId = $this->sanitizeInboundText($discordMessageId);
		$discordUserId = $this->sanitizeInboundText($discordUserId);
		$discordUsername = $this->sanitizeInboundText($discordUsername);
		$discordAvatarUrl = $this->sanitizeInboundText($discordAvatarUrl);
		$messageUrl = $this->sanitizeInboundText($messageUrl);

		if ($message === '') {
			return $this->apiError(XF::phrase('please_enter_valid_message'), 'message_required', [], 400);
		}

		if ($discordMessageId === '') {
			return $this->apiError(XF::phrase('required_field_missing', ['field' => 'discord_message_id']), 'discord_message_id_required', [], 400);
		}

		$discordMessageId = substr($discordMessageId, 0, 50);

		/** @var Message|null $existing */
		$existing = $this->finder('Shoutbox:Message')
			->where('discord_message_id', $discordMessageId)
			->fetchOne();
		if ($existing) {
			return $this->apiSuccess([
				'duplicate' => true,
				'message_id' => $existing->id,
			]);
		}

		$body = trim($message);
		$body = $this->emojiTransformer()->fromDiscord($body);
		$body = substr($body, 0, 500);

		if ($this->containsDiscordPings($body)) {
			return $this->apiError('Discord mentions and pings are not allowed in the shoutbox.', 'discord_pings_not_allowed', [], 400);
		}

		$linkedForumUserId = null;
		if ($discordUserId !== '') {
			$linkedForumUserId = $this->tryGetLinkedForumUserId($discordUserId);
		}

		$posterUserId = null;
		$storeDiscordIdentity = true;
		if ($linkedForumUserId) {
			// Post as the linked forum user.
			$linkedUser = $this->em()->find('XF:User', $linkedForumUserId);
			if ($linkedUser) {
				$posterUserId = $linkedUser->user_id;
				$storeDiscordIdentity = false;
			}
		}

		/** @var Message $entity */
		$entity = $this->em()->create('Shoutbox:Message');
		$entity->user_id = $posterUserId;
		$entity->message = $body;
		$entity->discord_message_id = $discordMessageId;
		$entity->is_discord_message = true;
		if ($storeDiscordIdentity) {
			$entity->discord_user_id = $discordUserId !== '' ? substr($discordUserId, 0, 20) : null;
			$entity->discord_username = $discordUsername !== '' ? substr($discordUsername, 0, 100) : null;
			$entity->discord_avatar_url = $discordAvatarUrl !== '' ? substr($discordAvatarUrl, 0, 255) : null;
		}
		$entity->save();

		/** @var MessageRepo $repo */
		$repo = $this->repository('Shoutbox:Message');
		$repo->setLastIdCache($entity->id);
		$repo->bumpGeneration();

		return $this->apiSuccess([
			'message_id' => $entity->id,
		]);
	}

	/**
	 * Delete a shoutbox message when it's deleted from Discord.
	 *
	 * POST /api/shoutbox/discord/delete
	 *
	 * @return ApiResult JSON response with deletion status
	 */
	public function actionPostDelete(ParameterBag $params): ApiResult|Error
	{
		$this->assertApiScopeByRequestMethod('shoutbox');

		$discordMessageId = $this->filter('discord_message_id', 'str');
		$discordMessageId = $this->sanitizeInboundText($discordMessageId);

		if ($discordMessageId === '') {
			return $this->apiError(XF::phrase('required_field_missing', ['field' => 'discord_message_id']), 'discord_message_id_required', [], 400);
		}

		$discordMessageId = substr($discordMessageId, 0, 50);

		/** @var Message|null $existing */
		$existing = $this->finder('Shoutbox:Message')
			->where('discord_message_id', $discordMessageId)
			->fetchOne();

		if ($existing) {
			$existing->setOption(Message::OPTION_SKIP_DISCORD_DELETE, true);
			$existing->setOption(Message::OPTION_SKIP_GENERATION_BUMP, true);
			$existing->delete();
		}

		/** @var MessageRepo $repo */
		$repo = $this->repository('Shoutbox:Message');
		$maxId = XF::db()->fetchOne('SELECT MAX(id) FROM shoutbox_message');
		$repo->setLastIdCache($maxId);
		$repo->bumpGeneration();

		return $this->apiSuccess();
	}

	public function actionPostUpdate(ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('shoutbox');

		$message = $this->filter('message', 'str');
		$discordMessageId = $this->filter('discord_message_id', 'str');
		$message = $this->sanitizeInboundText($message);
		$discordMessageId = $this->sanitizeInboundText($discordMessageId);

		if ($message === '') {
			return $this->apiError(XF::phrase('please_enter_valid_message'), 'message_required', [], 400);
		}

		if ($discordMessageId === '') {
			return $this->apiError(XF::phrase('required_field_missing', ['field' => 'discord_message_id']), 'discord_message_id_required', [], 400);
		}

		/** @var Message|null $existing */
		$existing = $this->finder('Shoutbox:Message')
			->where('discord_message_id', $discordMessageId)
			->fetchOne();

		if (!$existing) {
			// No-op if the message doesn't exist locally.
			return $this->apiSuccess(['updated' => false]);
		}

		$existing->message = $this->emojiTransformer()->fromDiscord($message);
		$existing->save();

		/** @var MessageRepo $repo */
		$repo = $this->repository('Shoutbox:Message');
		$maxId = XF::db()->fetchOne('SELECT MAX(id) FROM shoutbox_message');
		$repo->setLastIdCache($maxId);
		$repo->bumpGeneration();

		return $this->apiSuccess();
	}

	public function actionPostTimeout(ParameterBag $params): ApiResult|Error
	{
		$this->assertApiScopeByRequestMethod('shoutbox');

		$discordUserId = $this->filter('discord_user_id', 'str');
		$moderatorDiscordUserId = $this->filter('moderator_discord_user_id', 'str');
		$timeoutReason = $this->filter('timeout_reason', 'str');
		$durationSeconds = $this->filter('duration_seconds', 'uint');
		$durationMs = $this->filter('duration_ms', 'uint');
		$timeoutUntilMs = $this->filter('timeout_until_ms', 'uint');

		$discordUserId = $this->sanitizeInboundText($discordUserId);
		if ($discordUserId === '') {
			return $this->apiError(
				XF::phrase('required_field_missing', ['field' => 'discord_user_id']),
				'discord_user_id_required',
				[],
				400
			);
		}
		$discordUserId = substr($discordUserId, 0, 20);

		$moderatorDiscordUserId = $this->sanitizeInboundText($moderatorDiscordUserId);
		if ($moderatorDiscordUserId !== '') {
			$moderatorDiscordUserId = substr($moderatorDiscordUserId, 0, 20);
		} else {
			$moderatorDiscordUserId = '';
		}

		$now = XF::$time;
		$endDate = 0;
		if ($timeoutUntilMs > 0) {
			$endDate = (int)floor($timeoutUntilMs / 1000);
		} else if ($durationMs > 0) {
			$endDate = $now + (int)ceil($durationMs / 1000);
		} else if ($durationSeconds > 0) {
			$endDate = $now + (int)$durationSeconds;
		}

		if ($endDate <= $now) {
			return $this->apiError(
				XF::phrase('please_enter_valid_duration'),
				'invalid_duration',
				[],
				400
			);
		}

		$linkedForumUserId = $this->tryGetLinkedForumUserId($discordUserId);
		if (!$linkedForumUserId) {
			// Not linked: do nothing, but return success so the bot doesn't treat it as an error.
			return $this->apiSuccess([
				'linked' => false,
				'banned' => false,
				'end_date' => $endDate,
			]);
		}

		/** @var User|null $user */
		$user = $this->em()->find('XF:User', $linkedForumUserId);
		if (!$user) {
			return $this->apiSuccess([
				'linked' => false,
				'banned' => false,
				'end_date' => $endDate,
			]);
		}

		try {
			$banUserId = 0;
			if ($moderatorDiscordUserId !== '') {
				$linkedModeratorUserId = $this->tryGetLinkedForumUserId($moderatorDiscordUserId);

				if ($linkedModeratorUserId) {
					$banUserId = (int)$linkedModeratorUserId;
				}
			}

			/** @var ShoutboxBan|null $ban */
			$ban = $this->em()->find('Shoutbox:ShoutboxBan', $user->user_id);
			$expiresOn = date('Y-m-d H:i:s', $endDate);
			$changed = false;

			if (!$ban) {
				$ban = $this->em()->create('Shoutbox:ShoutboxBan');
				$ban->user_id = $user->user_id;
				$ban->ban_user_id = $banUserId;
				$ban->reason = ($timeoutReason ? $timeoutReason . ' (From Discord)' : 'Discord timeout');
				$ban->banned_on = date('Y-m-d H:i:s', $now);
				$ban->expires_on = $expiresOn;
				$changed = true;
			} else {
				if ($banUserId && (int)$ban->ban_user_id !== $banUserId) {
					$ban->ban_user_id = $banUserId;
					$changed = true;
				}

				if ($ban->expires_on !== null) {
					$currentExpiresTs = $ban->expires_on ? strtotime($ban->expires_on) : 0;

          if (!$currentExpiresTs || $currentExpiresTs < $endDate) {
						$ban->expires_on = $expiresOn;
						$changed = true;
					}
				}
			}

			if ($changed) {
				$ban->save();
			}

			return $this->apiSuccess([
				'linked' => true,
				'banned' => true,
				'changed' => $changed,
				'user_id' => $user->user_id,
				'ban_user_id' => (int)$ban->ban_user_id,
				'expires_on' => $ban->expires_on,
			]);
		} catch (Throwable $e) {
			XF::logException($e, false, 'Discord timeout shoutbox ban failed: ');

			return $this->apiError(
				XF::phrase('an_error_occurred_while_processing_your_request'),
				'timeout_failed',
				[],
				500
			);
		}
	}

	public function actionPostUntimeout(ParameterBag $params): ApiResult|Error
	{
		$this->assertApiScopeByRequestMethod('shoutbox');

		$discordUserId = $this->filter('discord_user_id', 'str');
		$moderatorDiscordUserId = $this->filter('moderator_discord_user_id', 'str');
		$untimeoutReason = $this->filter('untimeout_reason', 'str');

		$discordUserId = $this->sanitizeInboundText($discordUserId);
		if ($discordUserId === '') {
			return $this->apiError(
				XF::phrase('required_field_missing', ['field' => 'discord_user_id']),
				'discord_user_id_required',
				[],
				400
			);
		}
		$discordUserId = substr($discordUserId, 0, 20);

		$moderatorDiscordUserId = $this->sanitizeInboundText($moderatorDiscordUserId);
		if ($moderatorDiscordUserId !== '') {
			$moderatorDiscordUserId = substr($moderatorDiscordUserId, 0, 20);
		} else {
			$moderatorDiscordUserId = '';
		}

		$linkedForumUserId = $this->tryGetLinkedForumUserId($discordUserId);
		if (!$linkedForumUserId) {
			return $this->apiSuccess([
				'linked' => false,
				'unbanned' => false,
			]);
		}

		/** @var User|null $user */
		$user = $this->em()->find('XF:User', $linkedForumUserId);
		if (!$user) {
			return $this->apiSuccess([
				'linked' => false,
				'unbanned' => false,
			]);
		}

		try {
			/** @var ShoutboxBan|null $ban */
			$ban = $this->em()->find('Shoutbox:ShoutboxBan', $user->user_id);
			if (!$ban) {
				return $this->apiSuccess([
					'linked' => true,
					'unbanned' => false,
					'user_id' => $user->user_id,
				]);
			}

			$ban->delete();

			return $this->apiSuccess([
				'linked' => true,
				'unbanned' => true,
				'user_id' => $user->user_id,
				'moderator_discord_user_id' => $moderatorDiscordUserId !== '' ? $moderatorDiscordUserId : null,
				'unban_reason' => $untimeoutReason !== '' ? $untimeoutReason : null,
			]);
		} catch (Throwable $e) {
			XF::logException($e, false, 'Discord untimeout shoutbox unban failed: ');
			return $this->apiError(
				XF::phrase('an_error_occurred_while_processing_your_request'),
				'untimeout_failed',
				[],
				500
			);
		}
	}
}
