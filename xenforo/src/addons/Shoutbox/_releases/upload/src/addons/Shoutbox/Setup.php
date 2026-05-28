<?php

namespace Shoutbox;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\Db\Schema\Alter;

/**
 * Dime why are we using raw SQL to generate thet ables???
 * Well thats because XenForo doesn't have native support for timestamp/datetime column types...
 */
class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	protected function ensureBaseTablesExist(): void
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		if (!$sm->tableExists('shoutbox_message')) {
			$db->query("
        CREATE TABLE `shoutbox_message` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned DEFAULT NULL,
          `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `is_discord_message` tinyint(1) NOT NULL,
          `discord_message_id` varchar(100) DEFAULT NULL,
          `discord_user_id` varchar(20) DEFAULT NULL,
          `discord_username` varchar(100) DEFAULT NULL,
          `discord_avatar_url` varchar(255) DEFAULT NULL,
          `is_message_edited` tinyint(1) NOT NULL,
          `created_on` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
			");
		}

		if (!$sm->tableExists('shoutbox_ban')) {
			$db->query("
				CREATE TABLE `shoutbox_ban` (
					`user_id` int(10) unsigned NOT NULL,
					`banned_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`expires_on` datetime DEFAULT NULL,
					`reason` varchar(255) NOT NULL DEFAULT '',
					`ban_user_id` int(10) unsigned NOT NULL DEFAULT 0,
					PRIMARY KEY (`user_id`),
					KEY `banned_on` (`banned_on`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}
	}

	protected function createBanHistoryTable(): void
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists('shoutbox_ban_history')) {
			return;
		}

		$this->db()->query("
			CREATE TABLE `shoutbox_ban_history` (
				`ban_history_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`user_id` int(10) unsigned NOT NULL,
				`username` varchar(50) NOT NULL DEFAULT '',
				`banned_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`expires_on` datetime DEFAULT NULL,
				`reason` varchar(255) NOT NULL DEFAULT '',
				`ban_user_id` int(10) unsigned NOT NULL DEFAULT 0,
				`ban_username` varchar(50) NOT NULL DEFAULT '',
				`unbanned_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`unban_user_id` int(10) unsigned NOT NULL DEFAULT 0,
				`unban_username` varchar(50) NOT NULL DEFAULT '',
				PRIMARY KEY (`ban_history_id`),
				KEY `user_unbanned_on` (`user_id`, `unbanned_on`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}

	protected function migrateLegacyMessages(): void
	{
		$sm = $this->schemaManager();
		if (!$sm->tableExists('shoutbox') || !$sm->tableExists('shoutbox_message')) {
			return;
		}

		$db = $this->db();
		$legacyCount = (int)$db->fetchOne('SELECT COUNT(*) FROM shoutbox');
		if (!$legacyCount) {
			return;
		}

		$columns = [
			'id',
			'user_id',
			'created_on',
			'message',
			'discord_message_id',
		];

		$selects = [
			'legacy.id',
			'legacy.user_id',
			'FROM_UNIXTIME(legacy.`date`)',
			'COALESCE(legacy.message, \'\')',
			'NULLIF(SUBSTRING(legacy.discord_message_id, 1, 50), \'\')',
		];

		// All of our messages before were not from Discord
		$columns[] = 'is_discord_message';
		$selects[] = '0';

		// Legacy messages were never edited
		$columns[] = 'is_message_edited';
		$selects[] = '0';

		$sql = sprintf(
			'INSERT INTO shoutbox_message (%s) SELECT %s FROM shoutbox AS legacy ' .
			'LEFT JOIN shoutbox_message AS current ON current.id = legacy.id WHERE current.id IS NULL',
			implode(', ', $columns),
			implode(', ', $selects)
		);

		$db->query($sql);

		$missing = (int)$db->fetchOne('
			SELECT COUNT(*)
			FROM shoutbox AS legacy
			LEFT JOIN shoutbox_message AS current ON current.id = legacy.id
			WHERE current.id IS NULL
		');
		if ($missing === 0) {
			$sm->dropTable('shoutbox');
		}
	}

	public function install(array $stepParams = [])
	{
		$db = $this->db();

		$db->query("
      CREATE TABLE `shoutbox_message` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` int(10) unsigned DEFAULT NULL,
        `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `is_discord_message` tinyint(1) NOT NULL,
        `discord_message_id` varchar(100) DEFAULT NULL,
        `discord_user_id` varchar(20) DEFAULT NULL,
        `discord_username` varchar(100) DEFAULT NULL,
        `discord_avatar_url` varchar(255) DEFAULT NULL,
        `is_message_edited` tinyint(1) NOT NULL,
        `created_on` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
		");

		$db->query("
			CREATE TABLE `shoutbox_ban` (
				`user_id` int(10) unsigned NOT NULL,
				`banned_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`expires_on` datetime DEFAULT NULL,
				`reason` varchar(255) NOT NULL DEFAULT '',
				`ban_user_id` int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY (`user_id`),
				KEY `banned_on` (`banned_on`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		$this->createBanHistoryTable();
	}


	public function uninstall(array $stepParams = [])
	{
		// Do not drop tables automatically to avoid data loss.
	}

	public function upgrade1000200Step1()
	{
		$this->ensureBaseTablesExist();
		$this->createBanHistoryTable();
	}

	public function upgrade1000200Step2()
	{
		$this->migrateLegacyMessages();
	}
}
