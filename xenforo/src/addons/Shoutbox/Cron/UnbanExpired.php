<?php

namespace Shoutbox\Cron;

use Exception;
use XF;

class UnbanExpired
{
	public static function run()
	{
		$db = XF::db();
		$now = date('Y-m-d H:i:s');

		$expiredBans = $db->fetchAll('
			SELECT sb.user_id, sb.banned_on, sb.expires_on, sb.reason, sb.ban_user_id
			FROM shoutbox_ban AS sb
			WHERE sb.expires_on IS NOT NULL
			AND sb.expires_on <= ?
			ORDER BY sb.expires_on ASC
			LIMIT 200
		', [$now]);

		if (!$expiredBans) {
			return;
		}

		$db->beginTransaction();

		try {
			foreach ($expiredBans as $ban) {
				$db->insert('shoutbox_ban_history', [
					'user_id' => (int)$ban['user_id'],
					'banned_on' => $ban['banned_on'],
					'expires_on' => $ban['expires_on'],
					'reason' => (string)($ban['reason'] ?? ''),
					'ban_user_id' => (int)($ban['ban_user_id'] ?? 0),
					'unbanned_on' => $now,
					'unban_user_id' => 0,
				], false, false);

				$db->delete('shoutbox_ban', 'user_id = ?', (int)$ban['user_id']);
			}

			$db->commit();
		} catch (Exception $e) {
			$db->rollback();
			XF::logException($e, false);
		}
	}
}
