<?php

namespace PeterSav\GModInterface\Service\Stats;

use XF\Service\AbstractService;
use PeterSav\GModInterface\Repository\Stats\Stats as StatsRepo;

class Provider extends AbstractService {
	protected StatsRepo $statsRepo;

	public function __construct(\XF\App $app) {
		parent::__construct($app);
		$this->statsRepo = $app->repository('PeterSav\\GModInterface:Stats\\Stats');
	}

	/**
	 * Build hub view params
	 *
	 * @param string|null $mySteamId
	 * @param string|null $searchQuery
	 *
	 * @return array<string, mixed>
	 */
	public function getHubViewParams($mySteamId, $searchQuery = null): array {
		$leaderboards = $this->decorateLeaderboards($this->statsRepo->getLeaderboards());
		$searchQuery = $searchQuery !== null ? trim($searchQuery) : '';
		$searchResults = [];

		if ($searchQuery !== '') {
			$searchResults = $this->statsRepo->searchPlayers($searchQuery, 25);
		}

		return [
			'leaderboards' => $leaderboards,
			'mySteamId' => $mySteamId,
			'searchQuery' => $searchQuery,
			'searchResults' => $searchResults,
		];
	}

	/**
	 * Build player stats view params
	 *
	 * @param string $inputSteamId
	 *
	 * @return array{ok: bool, error?: string, viewParams?: array<string, mixed>}
	 */
	public function getPlayerViewParams(string $inputSteamId): array {
		$steamid64 = $this->toCommunityId($inputSteamId);

		$playerStatsByServer = $this->statsRepo->getPlayerStatsByServer($steamid64);
		if (!$playerStatsByServer) {
			return [
				'ok' => false,
				'error' => 'No player stats found for provided SteamID',
			];
		}

		$totalTimeMinutes = 0;
		$earliestDate = null;
		foreach ($playerStatsByServer as $row) {
			$totalTimeMinutes += $row['minutes'] ?? 0;
			if (!empty($row['first_joined_date']) && ($earliestDate === null || $row['first_joined_date'] < $earliestDate)) {
				$earliestDate = $row['first_joined_date'];
			}
		}

		foreach ($playerStatsByServer as &$row) {
			$row['playTime'] = $this->formatMinutes($row['minutes'] ?? 0);
			$row['derived'] = $this->buildDerivedStats($row);
		}
		unset($row);

		$playerStats = $this->aggregatePlayerStats($playerStatsByServer);
		$playerStats['in_game_name'] = $playerStatsByServer[0]['in_game_name'];
		$playerStats['first_joined_date'] = $earliestDate ?? $playerStatsByServer[0]['first_joined_date'];
		$playerStats['serverid'] = 1;

		$viewParams = [
			'steamid64' => $steamid64,
			'userName' => $playerStatsByServer[0]['in_game_name'],
			'steamAvatarURL' => $this->getSteamAvatar($steamid64),
			'totalPlayTime' => $this->formatMinutes($totalTimeMinutes),
			'playerStatsByServer' => $playerStatsByServer,
			'playerStats' => $playerStats,
			'derived' => $this->buildDerivedStats($playerStats),
		];

		return [
			'ok' => true,
			'viewParams' => $viewParams,
		];
	}

	private function aggregatePlayerStats(array $playerStatsByServer): array {
		return array_reduce($playerStatsByServer, function ($carry, $item) {
			foreach (array_keys($item) as $key) {
				if (in_array($key, [
					'date', 'serverid', 'first_joined_date', 'last_seen_date',
					'in_game_name', 'serverid', 'server_hostname', 'server_nice_name',
					'playTime', 'derived'
				], true)) {
					continue;
				}
				if (!isset($carry[$key])) {
					$carry[$key] = 0;
				}

				if (is_numeric($item[$key])) {
					$carry[$key] += $item[$key];
				}
			}
			return $carry;
		}, []);
	}

	private function safeDiv($numerator, $denominator, $default = 0.0) {
		$numerator = (float)($numerator ?? 0);
		$denominator = (float)($denominator ?? 0);
		if ($denominator <= 0) {
			return (float)$default;
		}
		return $numerator / $denominator;
	}

	private function roundPct($value) {
		return round((float)$value, 1);
	}

	private function roundRatio($value) {
		return round((float)$value, 2);
	}

	private function formatMinutes($minutes) {
		$minutes = (int)$minutes;
		$d = (int)floor($minutes / 1440);
		$h = (int)floor(($minutes - $d * 1440) / 60);
		$m = (int)($minutes - ($d * 1440) - ($h * 60));
		return $d . 'd, ' . $h . 'h, and ' . $m . 'm';
	}

	private function buildDerivedStats(array $stats) {
		$innocentRounds = $stats['innocent_rounds'] ?? 0;
		$traitorRounds = $stats['traitor_rounds'] ?? 0;
		$detectiveRounds = $stats['detective_rounds'] ?? 0;

		$innocentWins = $stats['innocent_wins'] ?? 0;
		$traitorWins = $stats['traitor_wins'] ?? 0;
		$detectiveWins = $stats['detective_wins'] ?? 0;

		$kills = $stats['kills'] ?? 0;
		$deaths = $stats['deaths'] ?? 0;
		$headshots = $stats['headshots'] ?? 0;

		$totalRounds = $innocentRounds + $traitorRounds + $detectiveRounds;
		$totalWins = $innocentWins + $traitorWins + $detectiveWins;

		$teamKills = (
			($stats['innocent_kills_innocent'] ?? 0) +
			($stats['innocent_kills_detective'] ?? 0) +
			($stats['detective_kills_innocent'] ?? 0) +
			($stats['detective_kills_detective'] ?? 0) +
			($stats['traitor_kills_traitor'] ?? 0)
		);

		$roles = [];

		$roles['innocent'] = [
			'rounds' => $innocentRounds,
			'wins' => $innocentWins,
			'losses' => $stats['innocent_losses'] ?? 0,
			'survived' => $stats['innocent_rounds_survived'] ?? 0,
			'deaths' => $stats['innocent_deaths'] ?? 0,
			'enemyKills' => $stats['innocent_kills_traitor'] ?? 0,
			'teamKills' => ($stats['innocent_kills_innocent'] ?? 0) + ($stats['innocent_kills_detective'] ?? 0),
		];
		$roles['innocent']['winRate'] = $this->roundPct($this->safeDiv($roles['innocent']['wins'], max($roles['innocent']['rounds'], 1)) * 100);
		$roles['innocent']['survivalRate'] = $this->roundPct($this->safeDiv($roles['innocent']['survived'], max($roles['innocent']['rounds'], 1)) * 100);
		$roles['innocent']['kd'] = $this->roundRatio($this->safeDiv($roles['innocent']['enemyKills'], max($roles['innocent']['deaths'], 1)));

		$roles['detective'] = [
			'rounds' => $detectiveRounds,
			'wins' => $detectiveWins,
			'losses' => $stats['detective_losses'] ?? 0,
			'survived' => $stats['detective_rounds_survived'] ?? 0,
			'deaths' => $stats['detective_deaths'] ?? 0,
			'enemyKills' => $stats['detective_kills_traitor'] ?? 0,
			'teamKills' => ($stats['detective_kills_innocent'] ?? 0) + ($stats['detective_kills_detective'] ?? 0),
		];
		$roles['detective']['winRate'] = $this->roundPct($this->safeDiv($roles['detective']['wins'], max($roles['detective']['rounds'], 1)) * 100);
		$roles['detective']['survivalRate'] = $this->roundPct($this->safeDiv($roles['detective']['survived'], max($roles['detective']['rounds'], 1)) * 100);
		$roles['detective']['kd'] = $this->roundRatio($this->safeDiv($roles['detective']['enemyKills'], max($roles['detective']['deaths'], 1)));

		$roles['traitor'] = [
			'rounds' => $traitorRounds,
			'wins' => $traitorWins,
			'losses' => $stats['traitor_losses'] ?? 0,
			'survived' => $stats['traitor_rounds_survived'] ?? 0,
			'deaths' => $stats['traitor_deaths'] ?? 0,
			'enemyKills' => ($stats['traitor_kills_innocent'] ?? 0) + ($stats['traitor_kills_detective'] ?? 0),
			'teamKills' => $stats['traitor_kills_traitor'] ?? 0,
			'topKills' => $stats['traitor_round_top_kills'] ?? 0,
		];
		$roles['traitor']['winRate'] = $this->roundPct($this->safeDiv($roles['traitor']['wins'], max($roles['traitor']['rounds'], 1)) * 100);
		$roles['traitor']['survivalRate'] = $this->roundPct($this->safeDiv($roles['traitor']['survived'], max($roles['traitor']['rounds'], 1)) * 100);
		$roles['traitor']['kd'] = $this->roundRatio($this->safeDiv($roles['traitor']['enemyKills'], max($roles['traitor']['deaths'], 1)));

		$specdmKills = ($stats['specdm_kills'] ?? 0);
		$specdmDeaths = ($stats['specdm_deaths'] ?? 0);

		return [
			'totalRounds' => $totalRounds,
			'totalWins' => $totalWins,
			'winRate' => $this->roundPct($this->safeDiv($totalWins, max($totalRounds, 1)) * 100),
			'kdRatio' => $this->roundRatio($this->safeDiv($kills, max($deaths, 1))),
			'headshotPct' => $this->roundPct($this->safeDiv($headshots, max($kills, 1)) * 100),
			'teamKills' => $teamKills,
			'specdmKd' => $this->roundRatio($this->safeDiv($specdmKills, max($specdmDeaths, 1))),
			'roles' => $roles,
		];
	}

	private function decorateLeaderboards(array $leaderboards): array {
		$decorate = function (array $rows) {
			$rank = 0;
			foreach ($rows as &$row) {
				$rank++;
				$row['rank'] = $rank;
				$row['derived'] = $this->buildLeaderboardDerived($row);

				if (empty($row['in_game_name'])) {
					$row['in_game_name'] = $row['steamid64'];
				}
			}

			unset($row);

			return $rows;
		};

		return [
			'mostPlaytime' => $decorate($leaderboards['mostPlaytime'] ?? []),
			'mostKills' => $decorate($leaderboards['mostKills'] ?? []),
			'bestKd' => $decorate($leaderboards['bestKd'] ?? []),
			'bestWinRate' => $decorate($leaderboards['bestWinRate'] ?? []),
		];
	}

	private function buildLeaderboardDerived(array $row) {
		$kills = $row['kills'] ?? 0;
		$deaths = $row['deaths'] ?? 0;
		$headshots = $row['headshots'] ?? 0;
		$rounds = $row['rounds'] ?? 0;
		$wins = $row['wins'] ?? 0;

		return [
			'playTime' => $this->formatMinutes($row['minutes'] ?? 0),
			'kdRatio' => $this->roundRatio($this->safeDiv($kills, max($deaths, 1))),
			'headshotPct' => $this->roundPct($this->safeDiv($headshots, max($kills, 1)) * 100),
			'winRate' => $this->roundPct($this->safeDiv($wins, max($rounds, 1)) * 100),
		];
	}

	private function toCommunityId($id) {
		if (preg_match("/^STEAM_/", (string)$id)) {
			$parts = explode(":", (string)$id);
			return bcadd(bcadd(bcmul($parts[2], "2"), "76561197960265728"), $parts[1]);
		}

		if (is_numeric($id) && strlen((string)$id) < 16) {
			return bcadd((string)$id, "76561197960265728");
		}

		return (string)$id;
	}

	private function getSteamAvatar($steamid64) {
		$em = $this->app->em();
		$provider = $em->find('XF:ConnectedAccountProvider', 'steam');
		$steamAPIKey = $provider->getValue('options')["client_secret"];

		$url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key={$steamAPIKey}&steamids={$steamid64}";

		$json = file_get_contents($url);
		$data = json_decode($json, true);

		return $data["response"]["players"][0]["avatarfull"] ?? null;
	}
}
