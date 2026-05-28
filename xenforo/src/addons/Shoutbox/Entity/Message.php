<?php

namespace Shoutbox\Entity;

use Throwable;
use XF;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use Shoutbox\Repository\Message as MessageRepo;
use Shoutbox\Service\DiscordBotMessenger;

/**
 * Represents a single shoutbox message.
 *
 *
 * @property int $id Message ID (primary key)
 * @property int|null $user_id XenForo user ID (null for unlinked Discord users)
 * @property string|null $created_on DateTime when message was created
 * @property string $message Message text content (max 500 chars)
 * @property boolean $is_discord_message True if message was sent from Discord, false otherwise
 * @property boolean $is_message_edited True if message was edited since originally posted
 * @property string|null $discord_message_id Discord message ID for sync (max 50 chars)
 * @property string|null $discord_user_id Discord user snowflake ID (max 20 chars)
 * @property string|null $discord_username Discord display name for unlinked users (max 100 chars)
 * @property string|null $discord_avatar_url Discord avatar URL for unlinked users (max 255 chars)
 * @property User|null $User Related XenForo user entity (null for generic Discord posts)
 */
class Message extends Entity
{
	public const OPTION_SKIP_DISCORD_DELETE = 'skipDiscordDelete';
	public const OPTION_SKIP_GENERATION_BUMP = 'skipGenerationBump';

	/** Option to skip Discord deletion when a message is deleted in XenForo.
	 * Used to prevent loops when a message is deleted as a result of a Discord deletion.
	 */
	protected function _postDelete()
	{
		parent::_postDelete();

		if (!$this->getOption(self::OPTION_SKIP_DISCORD_DELETE) && $this->discord_message_id) {
			try {
				$discordBot = new DiscordBotMessenger(XF::app());
				$discordBot->deleteMessage($this->discord_message_id);
			} catch (Throwable $e) {
				XF::logException($e, false, 'Shoutbox Discord delete failed: ');
			}
		}

		if (!$this->getOption(self::OPTION_SKIP_GENERATION_BUMP)) {
			/** @var MessageRepo $repo */
			$repo = XF::repository('Shoutbox:Message');
			$maxId = (int)XF::db()->fetchOne('SELECT MAX(id) FROM shoutbox_message');
			$repo->setLastIdCache($maxId);
			$repo->bumpGeneration();
		}
	}

	protected function _postSave()
	{
		parent::_postSave();

		if ($this->isUpdate() && $this->isChanged('message')) {
			if (!$this->is_message_edited) {
				$this->fastUpdate('is_message_edited', true);
			}
		}
	}

	/**
	 * Define the database structure for shoutbox messages.
	 *
	 * Relations:
	 * - User: Many-to-one relationship with XF:User (nullable)
	 *
	 * @param Structure $structure XenForo structure definition object
	 * @return Structure Configured structure with table, columns, and relations
	 */
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'shoutbox_message';
		$structure->shortName = 'Shoutbox:Message';
		$structure->primaryKey = 'id';
		$structure->columns = [
			'id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'created_on' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'message' => ['type' => self::STR, 'required' => true],
			'is_discord_message' => ['type' => self::BOOL, 'default' => false],
			'is_message_edited' => ['type' => self::BOOL, 'default' => false],
			'discord_message_id' => ['type' => self::STR, 'default' => null, 'nullable' => true, 'maxLength' => 50],
			'discord_user_id' => ['type' => self::STR, 'default' => null, 'nullable' => true, 'maxLength' => 20],
			'discord_username' => ['type' => self::STR, 'default' => null, 'nullable' => true, 'maxLength' => 100],
			'discord_avatar_url' => ['type' => self::STR, 'default' => null, 'nullable' => true, 'maxLength' => 255],
		];

		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
		];

		$structure->options = [
			self::OPTION_SKIP_DISCORD_DELETE => false,
			self::OPTION_SKIP_GENERATION_BUMP => false,
		];

		return $structure;
	}
}
