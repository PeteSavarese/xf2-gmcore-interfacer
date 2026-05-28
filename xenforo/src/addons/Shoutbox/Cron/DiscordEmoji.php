<?php

namespace Shoutbox\Cron;

use Shoutbox\Service\DiscordEmoji\Fetcher;
use XF;

class DiscordEmoji
{
	public static function syncFromDiscord(): void
	{
		/** @var Fetcher $fetcher */
		$fetcher = XF::service('Shoutbox:DiscordEmoji\\Fetcher');
		$fetcher->fetchAndStore();
	}
}
