<?php

namespace PeterSav\GModInterface\Service\HLogs;

use XF\Service\AbstractService;
use PeterSav\GModInterface\Repository\HLogs\HLogs as HLogsRepo;
use PeterSav\GModInterface\Repository\Stats\Stats as StatsRepo;

class Provider extends AbstractService {
	protected HLogsRepo $repo;
	protected StatsRepo $statsRepo;
	private const ALT_CHECKER_BASE_URL = 'https://gmodaltchecker.com/check_all';

	public function __construct(\XF\App $app) {
		parent::__construct($app);
		$this->repo = $app->repository('PeterSav\\GModInterface:HLogs\\HLogs');
		$this->statsRepo = $app->repository('PeterSav\\GModInterface:Stats\\Stats');
	}

	/**
	 * Build view params for HLogs search page
	 *
	 * @param string|null $query
	 * @return array<string, mixed>
	 */
	public function getSearchViewParams($query = null): array {
		$query = $query !== null ? trim($query) : '';
		$results = [];

		if ($query !== '') {
			$results = $this->repo->searchPlayers($query, 25);
			$results = $this->decorateSearchResults($results, $query);
		}

		return [
			'searchQuery' => $query,
			'searchResults' => $results,
		];
	}

	/**
	 * Add computed display fields to search results rows
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @param string $query
	 * @return array<int, array<string, mixed>>
	 */
	private function decorateSearchResults(array $rows, string $query): array {
		foreach ($rows as &$row) {
			$row['matchPreviewHtml'] = $this->buildMatchPreviewHtml($row, $query);
			unset($row);
		}

		return $rows;
	}

	/**
	 * Build match preview HTML used in search results table
	 *
	 * @param array<string, mixed> $row
	 * @param string $query
	 * @return string
	 */
	private function buildMatchPreviewHtml(array $row, string $query): string {
		$lines = [];

		if (!empty($row['match_current_name'])) {
			$lines[] = 'Current name: ' . $this->highlightMatch($row['in_game_name'] ?? '', $query);
		}

		if (!empty($row['match_previous_name'])) {
			$matchedPrevious = $this->findMatchingPreviousName($row['previous_names'] ?? '', $query);

			if ($matchedPrevious !== null && $matchedPrevious !== '') {
				$lines[] = 'Previous names: ' . $this->highlightMatch($matchedPrevious, $query);
			} else {
				$lines[] = 'Previous names: ' . $this->highlightMatch($row['in_game_name'] ?? '', $query);
			}
		}

		if (!empty($row['match_steamid64'])) {
			$lines[] = 'SteamID64: ' . $this->highlightMatch($row['steamid64'] ?? '', $query);
		}

		if (!empty($row['match_steamid32'])) {
			$lines[] = 'SteamID32: ' . $this->highlightMatch($row['steamid32'] ?? '', $query);
		}

		if (!$lines) {
			return '';
		}

		return implode('<br>', $lines);
	}

	/**
	 * Find previous name string matching query
	 */
	private function findMatchingPreviousName(string $previousNamesJson, string $query): ?string {
		$previousNamesJson = trim($previousNamesJson);
		if ($previousNamesJson === '') {
			return null;
		}

		$decoded = json_decode($previousNamesJson, true);
		if (!is_array($decoded)) {
			return null;
		}

		foreach ($decoded as $name) {
			if (!is_string($name) || $name === '') {
				continue;
			}

			if ($this->posInsensitive($name, $query) !== false) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Return HTML-escaped string with first match wrapped in <b>
	 */
	private function highlightMatch(string $haystack, string $needle): string {
		if ($needle === '') {
			return htmlspecialchars($haystack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		$pos = $this->posInsensitive($haystack, $needle);
		if ($pos === false) {
			return htmlspecialchars($haystack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		$len = $this->strlenSafe($needle);

		$before = $this->substrSafe($haystack, 0, $pos);
		$match = $this->substrSafe($haystack, $pos, $len);
		$after = $this->substrSafe($haystack, $pos + $len);

		return htmlspecialchars($before, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
			. '<b>' . htmlspecialchars($match, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>'
			. htmlspecialchars($after, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * Find case-insensitive position of needle in haystack used for highlighting matches
	 */
	private function posInsensitive(string $haystack, string $needle) {
		if (function_exists('mb_stripos')) {
			return mb_stripos($haystack, $needle, 0, 'UTF-8');
		}

		return stripos($haystack, $needle);
	}

	/**
	 * UTF-8 safe substring helper
	 */
	private function substrSafe(string $value, int $start, ?int $length = null): string {
		if (function_exists('mb_substr')) {
			return $length === null
				? mb_substr($value, $start, null, 'UTF-8')
				: mb_substr($value, $start, $length, 'UTF-8');
		}

		return $length === null ? substr($value, $start) : substr($value, $start, $length);
	}

	/**
	 * UTF-8 safe string length helper
	 */
	private function strlenSafe(string $value): int {
		if (function_exists('mb_strlen')) {
			return mb_strlen($value, 'UTF-8');
		}

		return strlen($value);
	}

	/**
	 * Build view params for HLogs results page
	 * @param string $inputSteamId
	 * @param \XF\Entity\User $visitor
	 * @return array{ok: bool, error?: string, viewParams?: array<string, mixed>}
	 */
	public function getResultsViewParams(string $inputSteamId, \XF\Entity\User $visitor): array {
		$steamId64 = $this->toCommunityId($inputSteamId);
		$steamId = $this->toSteamId($inputSteamId);

		if ($steamId64 === false || $steamId === false) {
			return [
				'ok' => false,
				'error' => 'Invalid SteamID provided',
			];
		}

		$userId = $this->repo->getForumUserIdBySteamId64($steamId64);
		$forumAccount = $userId ? \XF::finder('XF:User')->where('user_id', $userId)->fetchOne() : null;

		$playerData = $this->repo->getPlayerData($steamId64);
		if ($playerData && isset($playerData['previous_names']) && is_string($playerData['previous_names']) && $playerData['previous_names'] !== '') {
			$decoded = json_decode($playerData['previous_names'], true);
			$playerData['previous_names'] = is_array($decoded) ? $decoded : [];
		}

		$playerStats = $this->repo->getPlayerStatsSummary($steamId64);

		$punishTableRaw = $this->repo->getPunishments($steamId);
		$playerPunishments = $this->processPunishments($punishTableRaw);

		$playerNotes = $this->repo->getNotes($steamId64);
		$playerNotes = $this->filterNotesByVisibility($playerNotes, $visitor);

		$bans = $this->repo->getBans($steamId);

		$playerStatsByServer = $this->statsRepo->getPlayerHLogsByServer($steamId64);
		$playerStatsByServer = $this->decorateHLogsOverview($playerStatsByServer, $punishTableRaw);

		$altCheckerData = $this->fetchAltCheckerData($steamId);

		$altCheckerBansFlat = $altCheckerData ? $this->getAltCheckerBansFlatFromData($altCheckerData, $steamId) : [];
		$altCheckerDirectAlts = $altCheckerData ? $this->getAltCheckerDirectAltsFromData($altCheckerData) : [];

		if (count($altCheckerBansFlat)) {
			// Sort by ban date desc
			usort($altCheckerBansFlat, static function (array $a, array $b): int {
				return (int)($b['banned'] ?? 0) <=> (int)($a['banned'] ?? 0);
			});
		}

		$viewParams = [
			'steamId' => $steamId,
			'steamId64' => $steamId64,
			'bans' => $bans,
			'altCheckerBansFlat' => $altCheckerBansFlat,
			'altCheckerDirectAlts' => $altCheckerDirectAlts,
			'altCheckerUrl' => self::ALT_CHECKER_BASE_URL,
			'forumAccount' => $forumAccount,
			'playerData' => $playerData,
			'playerStats' => $playerStats,
			'playerStatsByServer' => $playerStatsByServer ?? null,
			'playerPunishments' => $playerPunishments,
			'punishTable' => $punishTableRaw,
			'playerNotes' => $playerNotes,
		];

		return [
			'ok' => true,
			'viewParams' => $viewParams,
		];
	}

	/**
	 * Detect SteamID formats suitable for direct results view
	 */
	public function looksLikeSteamId(string $value): bool {
		$value = trim($value);
		if ($value === '') {
			return false;
		}

		if (preg_match('/^STEAM_\d+:\d+:\d+$/', $value)) {
			return true;
		}

		if (preg_match('/^\d{16,21}$/', $value)) {
			return true;
		}

		return false;
	}

	/**
	 * Convert SteamID inputs to SteamID64
	 * TODO: Move to helper class eventually since other systems use this like Stats
	 */
	private function toCommunityId(string $id) {
		$id = trim($id);

		if (preg_match('/^STEAM_/', $id)) {
			$parts = explode(':', $id);
			if (count($parts) !== 3) {
				return false;
			}
			if (!is_numeric($parts[1]) || !is_numeric($parts[2])) {
				return false;
			}
			return bcadd(bcadd(bcmul($parts[2], '2'), '76561197960265728'), $parts[1]);
		}

		if (preg_match('/^\d{16,21}$/', $id)) {
			return $id;
		}

		if (preg_match('/^\d+$/', $id)) {
			if (strlen($id) >= 16) {
				return $id;
			}
			return bcadd($id, '76561197960265728');
		}

		return false;
	}

	/**
	 * Convert SteamID inputs to STEAM_0:Y:Z
	 * TODO: Move to helper class eventually since other systems use this like Stats
	 */
	private function toSteamId(string $id) {
		$id = trim($id);

		if (preg_match('/^STEAM_\d+:\d+:\d+$/', $id)) {
			return $id;
		}

		if (!preg_match('/^\d+$/', $id)) {
			return false;
		}

		if (strlen($id) >= 16) {
			$idVal = (int)$id;
			$z = (int)floor(($idVal - 76561197960265728) / 2);
			$y = $idVal % 2;
			return 'STEAM_0:' . $y . ':' . $z;
		}

		$idVal = (int)$id;
		$z = (int)floor($idVal / 2);
		$y = $idVal % 2;

		return 'STEAM_0:' . $y . ':' . $z;
	}

	/**
	 * Decorate per-server HLogs stats for punishment counts
	 *
	 * @param array<int, array<string, mixed>> $stats
	 * @param array<int, array<string, mixed>> $punishments
	 * @return array<int, array<string, mixed>>
	 */
	private function decorateHLogsOverview(array $stats, array $punishments = []): array {
		$punishmentsByServer = [];

		// Get punishment counts grouped by server
		foreach ($punishments as $punishment) {
			$sid = ($punishment['server'] ?? 0);

			if (!isset($punishmentsByServer[$sid])) {
				$punishmentsByServer[$sid] = ['ASlay' => 0, 'kick' => 0];
			}

			$type = $punishment['punishment'] ?? '';
			if ($type === 'ASlay' || $type === 'kick') {
				$punishmentsByServer[$sid][$type]++;
			}
		}

		foreach ($stats as &$row) {
			$serverId = ($row['serverid'] ?? 0);
			$minutes = ($row['minutes'] ?? 0);

			$row['playTime'] = $this->formatMinutesToTime($minutes);
			$row['slainCount'] = $punishmentsByServer[$serverId]['ASlay'] ?? 0;
			$row['kickCount'] = $punishmentsByServer[$serverId]['kick'] ?? 0;
		}

		return $stats;
	}

	/**
	 * Helper to format minutes to a human-readable string
	 * Identical logic to Templater::fnMinutesToTime but for service-level use
	 */
	private function formatMinutesToTime(int $minutes): string {
		$d = floor($minutes / 1440);
		$h = floor(($minutes - $d * 1440) / 60);
		$m = $minutes - ($d * 1440) - ($h * 60);

		return $d . "d, " . $h . "h, and " . $m . "m";
	}

	/**
	 * Group punishments rows by punishment type
	 *
	 * @param array<int, array<string, mixed>> $playerPunishments
	 * @return array<string, array<string, mixed>>|null
	 */
	private function processPunishments($playerPunishments) {
		if ($playerPunishments === 0 || $playerPunishments === false || $playerPunishments === null) {
			return null;
		}

		$punishTable = [];

		foreach ($playerPunishments as $row) {
			if (!array_key_exists($row['punishment'], $punishTable)) {
				$punishTable[$row['punishment']] = [];
				$punishTable[$row['punishment']]['type'] = $row['punishment'];
			}

			$punishTable[$row['punishment']][$row['id']] = [];
			$punishTable[$row['punishment']][$row['id']]['added'] = $row['added'];
			$punishTable[$row['punishment']][$row['id']]['admin'] = $row['admin'];
			$punishTable[$row['punishment']][$row['id']]['reason'] = $row['reason'];
			$punishTable[$row['punishment']][$row['id']]['server_name'] = $row['server_name'];
		}

		return $punishTable;
	}

	/**
	 * Filter staff notes based on visitor user groups
	 */
	private function filterNotesByVisibility(array $notes, \XF\Entity\User $visitor): array {
		$notesToGroups = [];
		$notesToGroups['admin'] = [3, 6, 8, 9, 5];
		$notesToGroups['lead'] = [6, 8, 9, 5];

		foreach ($notes as $key => $note) {
			if (($note['visibility'] ?? '') === 'admin' && !$visitor->isMemberOf($notesToGroups['admin'])) {
				unset($notes[$key]);

				continue;
			}
			if (($note['visibility'] ?? '') === 'lead' && !$visitor->isMemberOf($notesToGroups['lead'])) {
				unset($notes[$key]);

				continue;
			}
		}

		return array_values($notes);
	}

	/**
	 * Normalize AltChecker bans payload to a flat list
	 *
	 * @param array<string, mixed> $data
	 * @return array<int, array<string, mixed>>
	 */
	private function getAltCheckerBansFlatFromData(array $data, string $steamId32): array {
		$steamId32 = trim($steamId32);
		if ($steamId32 === '' || !preg_match('/^STEAM_\d+:\d+:\d+$/', $steamId32)) {
			return [];
		}

		$bansRoot = $data['bans'] ?? null;
		if (!is_array($bansRoot) || !$bansRoot) {
			return [];
		}

		$flat = [];

		foreach ($bansRoot as $accountSteamId => $communities) {
			if (!is_string($accountSteamId) || $accountSteamId === '' || !is_array($communities) || !$communities) {
				continue;
			}

			foreach ($communities as $communityName => $entries) {
				if (!is_string($communityName) || $communityName === '' || !is_array($entries) || !$entries) {
					continue;
				}

				foreach ($entries as $entry) {
					if (!is_array($entry)) {
						continue;
					}

					$reason = $entry['reason'] ?? '';
					if (is_string($reason) && $reason !== '') {
						$reason = html_entity_decode($reason, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					}

					$flat[] = [
						'communityName' => $communityName,
						'accountSteamId' => $accountSteamId,
						'server' => $entry['server'] ?? $communityName,
						'steamid' => $entry['steamid'] ?? $accountSteamId,
						'reason' => $reason,
						'length' => $entry['length'] ?? 0,
						'perma' => $entry['perma'] ?? false,
						'banned' => $entry['banned'] ?? 0,
						'unbanned' => $entry['unbanned'] ?? false,
						'banning_staff' => $entry['banning_staff'] ?? null,
						'banning_staff_name' => $entry['banning_staff_name'] ?? null,
						'unbanning_staff' => $entry['unbanning_staff'] ?? null,
					];
				}
			}
		}

		return $flat;
	}

	/**
	 * Normalize AltChecker direct_alts
	 *
	 * @param array<string, mixed> $data
	 * @return array<int, array<string, mixed>>
	 */
	private function getAltCheckerDirectAltsFromData(array $data): array {
		$direct = $data['direct_alts'] ?? null;
		if (!is_array($direct) || !$direct) {
			return [];
		}

		$alts = [];
		foreach ($direct as $altSteamId => $altData) {
			if (!is_string($altSteamId) || $altSteamId === '' || !is_array($altData)) {
				continue;
			}

			$name = $altData['name'] ?? '';
			if (is_string($name) && $name !== '') {
				$name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			}

			$servers = [];
			$serversRaw = $altData['servers'] ?? null;
			if (is_array($serversRaw)) {
				foreach ($serversRaw as $serverRow) {
					if (!is_array($serverRow)) {
						continue;
					}

					$serverName = $serverRow['name'] ?? '';
					if (!is_string($serverName) || $serverName === '') {
						continue;
					}

					$servers[] = [
						'name' => $serverName,
						'date' => $serverRow['date'] ?? null,
						'trusted' => (bool)($serverRow['trusted'] ?? false),
					];
				}
			}

			$alts[] = [
				'steamid' => $altSteamId,
				'name' => $name,
				'avatar' => $altData['avatar'] ?? '',
				'created' => $altData['created'] ?? 0,
				'servers' => $servers,
			];
		}

		return $alts;
	}

	/**
	 * Fetches all data from AltChecker for a given SteamID32
	 *
	 * @param string $steamId32 SteamID32 format of player to fetch
	 * @return array<string, mixed>|null
	 */
	private function fetchAltCheckerData(string $steamId32): ?array {
		$url = self::ALT_CHECKER_BASE_URL . '/' . rawurlencode($steamId32);

		try {
			$client = \XF::app()->http()->client();
			$response = $client->get($url, [
				'timeout' => 3.0,
				'connect_timeout' => 2.0,
				'http_errors' => false,
				'headers' => [
					'Accept' => 'application/json',
					'User-Agent' => 'XenForo-GModInterface/1.0',
				],
			]);

			if (method_exists($response, 'getStatusCode') && $response->getStatusCode() !== 200) {
				return null;
			}

			$body = $response->getBody();
			if ($body === '') {
				return null;
			}

			$decoded = json_decode($body, true);

			return is_array($decoded) ? $decoded : null;
		} catch (\Throwable $e) {
			return null;
		}
	}
}
