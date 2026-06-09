<?php

namespace Shoutbox\Repository;

use XF;
use XF\Mvc\Entity\Repository;

class DiscordEmojiRepository extends Repository
{
	protected static ?array $map = null;

	/**
	 * @return array<string, array{id: string, animated: bool}>
	 */
	public function getMapByName(): array
	{
		if (self::$map !== null) {
			return self::$map;
		}

		$rows = $this->db()->fetchAll(
			'SELECT name, discord_id, animated FROM shoutbox_discord_emoji'
		);

		$map = [];
		foreach ($rows as $row) {
			$map[$row['name']] = [
				'id' => $row['discord_id'],
				'animated' => (bool)$row['animated'],
			];
		}

		self::$map = $map;

		return $map;
	}

	public function upsert(string $name, string $discordId, bool $animated, string $source): void
	{
		if ($name === '' || $discordId === '') {
			return;
		}

		$this->db()->query(
			'REPLACE INTO shoutbox_discord_emoji (name, discord_id, animated, source, last_seen) VALUES (?, ?, ?, ?, ?)',
			[$name, $discordId, $animated ? 1 : 0, $source, XF::$time]
		);

		// Update cache
		if (self::$map !== null) {
			self::$map[$name] = ['id' => $discordId, 'animated' => $animated];
		}
	}

	public function invalidateCache(): void
	{
		self::$map = null;
	}
}
