<?php

namespace PeterSav\GModInterface\Repository\Stats;

use AllowDynamicProperties;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;
use PeterSav\GModInterface\Helper\CoreDatabase;

#[AllowDynamicProperties]
class Stats extends Repository {
	public function __construct(Manager $em, $identifier) {
		$em = clone $em;
		parent::__construct($em, $identifier);
	}

	/**
	 * Get core db adapter
	 *
	 * @return \XF\Db\AbstractAdapter
	 */
	public function coreDb() {
		return CoreDatabase::getCoreDbInstance();
	}

	public function getPlayerHLogsByServer(string $steamid64): array {
		$dbCore = $this->coreDb();

		$rows = $dbCore->fetchAll(
			"SELECT ps.serverid,
        ps.minutes,
        ps.created_on,
        ps.last_seen_on,
        s.niceName as server_nice_name
        FROM players_stats ps
        LEFT JOIN servers s ON ps.serverid = s.id
        WHERE ps.steamid64 = ?",
			$steamid64
		);

		return $rows;
	}

	/**
	 * Fetch player stats grouped by server
	 *
	 * @param string $steamid64
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPlayerStatsByServer(string $steamid64): array {
		$dbCore = $this->coreDb();

		$rows = $dbCore->fetchAll(
			"SELECT
                player_data.in_game_name,
                ps.serverid,
                servers.hostName as server_hostname,
                servers.niceName as server_nice_name,
                MIN(ps.created_on) as first_joined_date,
                MAX(ps.last_seen_on) as last_seen_date,
                SUM(ps.minutes) AS minutes,
                SUM(kills) as kills,
                SUM(traitor_round_top_kills) as traitor_round_top_kills,
                SUM(traitor_kills_traitor) as traitor_kills_traitor,
                SUM(traitor_kills_innocent) as traitor_kills_innocent,
                SUM(traitor_kills_detective) as traitor_kills_detective,
                SUM(innocent_kills_traitor) as innocent_kills_traitor,
                SUM(innocent_kills_innocent) as innocent_kills_innocent,
                SUM(innocent_kills_detective) as innocent_kills_detective,
                SUM(detective_kills_traitor) as detective_kills_traitor,
                SUM(detective_kills_innocent) as detective_kills_innocent,
                SUM(detective_kills_detective) as detective_kills_detective,
                SUM(deaths) as deaths,
                SUM(traitor_deaths) as traitor_deaths,
                SUM(innocent_deaths) as innocent_deaths,
                SUM(detective_deaths) as detective_deaths,
                SUM(suicides) as suicides,
                SUM(headshots) as headshots,
                SUM(killed_traitors_with_dna) as killed_traitors_with_dna,
                SUM(traitor_rounds) as traitor_rounds,
                SUM(traitor_wins) as traitor_wins,
                SUM(traitor_losses) as traitor_losses,
                SUM(traitor_rounds_survived) as traitor_rounds_survived,
                SUM(innocent_rounds) as innocent_rounds,
                SUM(innocent_wins) as innocent_wins,
                SUM(innocent_losses) as innocent_losses,
                SUM(innocent_rounds_survived) as innocent_rounds_survived,
                SUM(detective_rounds) as detective_rounds,
                SUM(detective_wins) as detective_wins,
                SUM(detective_losses) as detective_losses,
                SUM(detective_rounds_survived) as detective_rounds_survived,
                SUM(specdm_kills) as specdm_kills,
                SUM(specdm_deaths) as specdm_deaths,
                SUM(prop_kills) as prop_kills,
                SUM(force_kills) as force_kills,
                SUM(traitor_contribution) as traitor_contribution,
                SUM(innocent_contribution) as innocent_contribution,
                SUM(highest_kills_on_traitors) as highest_kills_on_traitors,
                SUM(highest_kills_on_innos) as highest_kills_on_innos
            FROM players_stats ps
            LEFT JOIN player_data ON ps.steamid64 = player_data.steamid64
            LEFT JOIN servers ON ps.serverid = servers.id
            WHERE ps.steamid64 = ?
            GROUP BY ps.serverid",
			$steamid64
		);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * Fetch public leaderboards
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function getLeaderboards(): array {
		$dbCore = $this->coreDb();

		$select = "
            ps.steamid64,
            COALESCE(pd.in_game_name, '') AS in_game_name,
            SUM(ps.minutes) AS minutes,
            SUM(ps.kills) AS kills,
            SUM(ps.deaths) AS deaths,
            SUM(ps.headshots) AS headshots,
            SUM(ps.innocent_rounds + ps.traitor_rounds + ps.detective_rounds) AS rounds,
            SUM(ps.innocent_wins + ps.traitor_wins + ps.detective_wins) AS wins,
            SUM(
                ps.innocent_kills_innocent +
                ps.innocent_kills_detective +
                ps.detective_kills_innocent +
                ps.detective_kills_detective +
                ps.traitor_kills_traitor
            ) AS teamkills
        ";

		$base = "
            FROM players_stats ps
            LEFT JOIN player_data pd ON ps.steamid64 = pd.steamid64
            GROUP BY ps.steamid64
        ";

		$mostPlaytime = $dbCore->fetchAll("SELECT {$select} {$base} ORDER BY minutes DESC LIMIT 10") ?: [];
		$mostKills = $dbCore->fetchAll("SELECT {$select} {$base} ORDER BY kills DESC LIMIT 10") ?: [];
		$bestKd = $dbCore->fetchAll(
			"SELECT {$select} {$base} HAVING SUM(ps.kills) >= 250 ORDER BY (SUM(ps.kills) / GREATEST(SUM(ps.deaths), 1)) DESC LIMIT 10"
		) ?: [];
		$bestWinRate = $dbCore->fetchAll(
			"SELECT {$select} {$base} HAVING SUM(ps.innocent_rounds + ps.traitor_rounds + ps.detective_rounds) >= 250 ORDER BY (SUM(ps.innocent_wins + ps.traitor_wins + ps.detective_wins) / GREATEST(SUM(ps.innocent_rounds + ps.traitor_rounds + ps.detective_rounds), 1)) DESC LIMIT 10"
		) ?: [];

		return [
			'mostPlaytime' => $mostPlaytime,
			'mostKills' => $mostKills,
			'bestKd' => $bestKd,
			'bestWinRate' => $bestWinRate,
		];
	}

	/**
	 * Search player_data by current name or previous names
	 *
	 * @param string $query
	 * @param int $limit
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function searchPlayers(string $query, int $limit = 25): array {
		$query = trim($query);
		if ($query === '') {
			return [];
		}

		$dbCore = $this->coreDb();

		$like = '%' . $query . '%';
		$limit = max(1, min(100, (int)$limit));

		$sql = "
            SELECT
                pd.steamid64,
                pd.in_game_name,
                UNIX_TIMESTAMP(MAX(ps.last_seen_on)) AS last_seen
            FROM player_data pd
            LEFT JOIN players_stats ps ON pd.steamid64 = ps.steamid64
            WHERE pd.in_game_name COLLATE utf8mb4_general_ci LIKE ?
               OR pd.previous_names COLLATE utf8mb4_general_ci LIKE ?
            GROUP BY pd.steamid64
            ORDER BY last_seen DESC
            LIMIT {$limit}
        ";

		$rows = $dbCore->fetchAll($sql, [$like, $like]);

		return is_array($rows) ? $rows : [];
	}
}
