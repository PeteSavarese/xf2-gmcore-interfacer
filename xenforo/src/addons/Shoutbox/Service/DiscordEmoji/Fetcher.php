<?php

namespace Shoutbox\Service\DiscordEmoji;

use Exception;
use Shoutbox\Repository\DiscordEmojiRepository;
use XF;
use XF\Service\AbstractService;

class Fetcher extends AbstractService
{
	protected const DISCORD_API_BASE = 'https://discord.com/api/v10';

	public function fetchAndStore(): int
	{
		$options = $this->app->options();
		$token = trim((string)($options->gmod_interfacer_discord_bot_token ?? ''));
		$guildId = trim((string)($options->gmod_interfacer_discord_server_guild ?? ''));

		if ($token === '' || $guildId === '') {
			XF::logError('Shoutbox DiscordEmoji Fetcher: missing bot token or guild ID; skipping API sync.');
			return 0;
		}

		try {
			$response = $this->app->http()->client()->get(
				self::DISCORD_API_BASE . "/guilds/{$guildId}/emojis",
				[
					'headers' => ['Authorization' => "Bot {$token}"],
					'http_errors' => false,
				]
			);
		} catch (Exception $e) {
			XF::logException($e, false, 'Shoutbox DiscordEmoji Fetcher: ');
			return 0;
		}

		$statusCode = $response->getStatusCode();
		if ($statusCode !== 200) {
			XF::logError("Shoutbox DiscordEmoji Fetcher: Discord API returned HTTP {$statusCode}");
			return 0;
		}

		$emojis = json_decode((string)$response->getBody(), true) ?: [];
		/** @var DiscordEmojiRepository $repo */
		$repo = $this->repository('Shoutbox:DiscordEmoji');

		$count = 0;
		foreach ($emojis as $e) {
			if (empty($e['name']) || empty($e['id'])) {
				continue;
			}
			$repo->upsert(
				substr((string)$e['name'], 0, 50),
				substr((string)$e['id'], 0, 50),
				!empty($e['animated']),
				'api'
			);
			$count++;
		}

		$repo->invalidateCache();
		return $count;
	}
}
