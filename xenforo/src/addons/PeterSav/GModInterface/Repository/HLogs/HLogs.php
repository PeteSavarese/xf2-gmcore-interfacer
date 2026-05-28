<?php

namespace PeterSav\GModInterface\Repository\HLogs;

use AllowDynamicProperties;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;
use PeterSav\GModInterface\Helper\CoreDatabase;

#[AllowDynamicProperties]
class HLogs extends Repository {
	public function __construct(Manager $em, $identifier) {
		$em = clone $em;
		parent::__construct($em, $identifier);
	}

	/**
	 * Get core database adapter
	 * @return \XF\Db\AbstractAdapter
	 */
	public function coreDb() {
		return CoreDatabase::getCoreDbInstance();
	}

	/**
	 * Get forum database adapter
	 * @return \XF\Db\AbstractAdapter
	 */
	public function forumDb() {
		return \XF::db();
	}

	/**
	 * Resolve XenForo user ID linked to steamid64
	 * @param string $steamId64
	 * @return int|null
	 */
	public function getForumUserIdBySteamId64(string $steamId64) {
		$db = $this->forumDb();

		$userId = $db->fetchOne(
			'SELECT user_id FROM xf_user_connected_account WHERE provider = ? AND provider_key = ? LIMIT 1',
			['steam', $steamId64]
		);

		return $userId ? (int)$userId : null;
	}

	/**
	 * Fetch player_data row for steamid64
	 * @param string $steamId64
	 * @return array<string, mixed>|null
	 */
	public function getPlayerData(string $steamId64) {
		$db = $this->coreDb();
		$row = $db->fetchRow(
			'SELECT pd.steamid64, pd.in_game_name, pd.previous_names,
				(SELECT UNIX_TIMESTAMP(MAX(last_seen_on)) FROM players_stats ps WHERE ps.steamid64 = pd.steamid64) AS last_seen
			FROM player_data pd
			WHERE pd.steamid64 = ?',
			$steamId64
		);

		return is_array($row) ? $row : null;
	}

	/**
	 * Fetch aggregate players_stats summary for steamid64
	 * @param string $steamId64
	 * @return array<string, mixed>|null
	 */
	public function getPlayerStatsSummary(string $steamId64) {
		$db = $this->coreDb();

		$row = $db->fetchRow("
										SELECT
									MIN(created_on) AS first_join_date,
									MAX(last_seen_on) AS last_seen_date,
											SUM(minutes) AS total_minutes
										FROM players_stats
										WHERE steamid64 = ?",
			$steamId64
		);

		if (!is_array($row)) {
			return null;
		}

		return $row;
	}

	/**
	 * Fetch punishments rows for STEAM_0:Y:Z
	 * @param string $steamId
	 * @return array<int, array<string, mixed>>
	 */
	public function getPunishments(string $steamId): array {
		$db = $this->coreDb();
		$rows = $db->fetchAll("
															SELECT p.*, s.niceName AS server_name
															FROM punishments p
															LEFT JOIN servers s ON p.server = s.id
															WHERE steamid = ?
															ORDER BY added DESC", $steamId);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * Fetch staff notes rows for steamid64
	 * @param string $steamId64
	 * @return array<int, array<string, mixed>>
	 */
	public function getNotes(string $steamId64): array {
		$db = $this->coreDb();
		$rows = $db->fetchAll("
															SELECT *
															FROM player_notes
															WHERE sidfor = ?
															ORDER BY id DESC", $steamId64);
		return is_array($rows) ? $rows : [];
	}

	/**
	 * Fetch ban rows for STEAM_0:Y:Z
	 * @param string $steamId
	 * @return array<int, array<string, mixed>>
	 */
	public function getBans(string $steamId): array {
		$db = $this->coreDb();
		$rows = $db->fetchAll(
			"
        SELECT bans.*, servers.niceName
        FROM bans
        LEFT JOIN servers ON bans.server = servers.id
        WHERE steamid = ?
        ORDER BY bans.id DESC
        ",
			$steamId
		);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * Search player_data by current name, previous names, steamid32, steamid64
	 * @param string $query
	 * @param int $limit
	 * @return array<int, array<string, mixed>>
	 */
	public function searchPlayers(string $query, int $limit = 25): array {
		$query = trim($query);
		if ($query === '') {
			return [];
		}

		$dbCore = $this->coreDb();
		$like = '%' . $query . '%';
		$limit = max(1, min(100, $limit));

		$steamId32Expr = '(CAST(pd.steamid64 AS UNSIGNED) - 76561197960265728)';

		$sql = "
            SELECT
                pd.steamid64,
                pd.in_game_name,
                pd.previous_names,
                UNIX_TIMESTAMP(MAX(ps.last_seen_on)) AS last_seen,
                {$steamId32Expr} AS steamid32,
                (pd.in_game_name COLLATE utf8mb4_general_ci LIKE ?) AS match_current_name,
                (pd.previous_names COLLATE utf8mb4_general_ci LIKE ?) AS match_previous_name,
                (pd.steamid64 LIKE ?) AS match_steamid64,
                ({$steamId32Expr} LIKE ?) AS match_steamid32
            FROM player_data pd
            LEFT JOIN players_stats ps ON pd.steamid64 = ps.steamid64
            WHERE pd.in_game_name COLLATE utf8mb4_general_ci LIKE ?
               OR pd.previous_names COLLATE utf8mb4_general_ci LIKE ?
               OR pd.steamid64 LIKE ?
               OR {$steamId32Expr} LIKE ?
            GROUP BY pd.steamid64
            ORDER BY last_seen DESC
            LIMIT {$limit}
        ";

		$rows = $dbCore->fetchAll($sql, [$like, $like, $like, $like, $like, $like, $like, $like]);
		return is_array($rows) ? $rows : [];
	}
}
