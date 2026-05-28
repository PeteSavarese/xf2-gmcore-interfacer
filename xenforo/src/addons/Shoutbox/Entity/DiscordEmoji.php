<?php

namespace Shoutbox\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property string $name
 * @property string $discord_id
 * @property bool $animated
 * @property string $source
 * @property int $last_seen
 */
class DiscordEmoji extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'shoutbox_discord_emoji';
		$structure->shortName = 'Shoutbox:DiscordEmoji';
		$structure->primaryKey = 'name';
		$structure->columns = [
			'name' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'discord_id' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
			'animated' => ['type' => self::BOOL, 'default' => false],
			'source' => [
				'type' => self::STR,
				'default' => 'ingest',
				'allowedValues' => ['api', 'ingest', 'admin'],
			],
			'last_seen' => ['type' => self::UINT, 'default' => 0],
		];

		return $structure;
	}

	public function getCdnUrl(): string
	{
		$ext = $this->animated ? 'gif' : 'webp';
		return "https://cdn.discordapp.com/emojis/{$this->discord_id}.{$ext}";
	}
}
