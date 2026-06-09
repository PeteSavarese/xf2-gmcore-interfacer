<?php

namespace PeterSav\GModInterface\Pub\Controller;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF\Db\Exception as DbException;

class Servers extends AbstractController {
	private const OFFLINE_THRESHOLD = 300; // 5 minutes

	private const STAFF_RANKS = [
		'owner' => 'Owner',
		'leadadmin' => 'Lead Administrator',
		'developer' => 'Developer',
		'admin' => 'Administrator',
		'mod' => 'Moderator',
		'advisor' => 'Advisor'
	];

	private const STAFF_RANK_BANNERS = [
		'owner' => 'userBanner--red',
		'leadadmin' => 'userBanner--orange',
		'developer' => 'userBanner userBanner-developer',
		'admin' => 'userBanner userBanner--royalBlue',
		'mod' => 'userBanner userBanner-moderator',
		'advisor' => 'userBanner userBanner-advisor'
	];

	private const STORE_RANKS = [
		1 => 'Supporter',
		2 => 'VIP',
		5 => 'Legendary',
	];

	private const STORE_RANK_BANNERS = [
		1 => 'userBanner-supporter',
		2 => 'userBanner-VIP',
		5 => 'userBanner-legendary'
	];

	public function actionIndex() {
		$dbCore = self::getCoreDbInstance();

		if ($dbCore) {
			$servers = $this->fetchAndProcessServers($dbCore);
		} else {
			\XF::logError('GModInterface: core DB unavailable in Servers controller; rendering servers page with empty gmod list.');
			$servers = [];
		}

		$viewParams = [
			'servers' => $servers
		];

		return $this->view('', 'gmod_servers', $viewParams);
	}

	private function fetchAndProcessServers(\XF\Db\Mysqli\Adapter $dbCore): array {
		$servers = $dbCore->fetchAll('SELECT * FROM servers');

		foreach ($servers as $serverId => &$server) {
			$server['playersJSON'] = json_decode($server['playersJSON'], true) ?? [];

			foreach ($server['playersJSON'] as $steamId => &$player) {
				$this->processPlayerRanks($player);
			}

			$server['offline'] = $this->isServerOffline($server['lastUpdate']);
		}

		return $servers;
	}

	private function processPlayerRanks(array &$player): void {
		if (isset($player['staff_rank']) &&
			$player['staff_rank'] !== 'not' &&
			isset(self::STAFF_RANKS[$player['staff_rank']])) {

			$rankName = self::STAFF_RANKS[$player['staff_rank']];
			$rankBanner = self::STAFF_RANK_BANNERS[$player['staff_rank']];
			$player['userBannerCode'] = sprintf(
				'<em class="userBanner %s">%s</em>',
				$rankBanner,
				$rankName
			);
		}

		if (isset($player['donor_rank']) &&
			$player['donor_rank'] !== 0 &&
			isset(self::STORE_RANKS[$player['donor_rank']])) {

			$donorName = self::STORE_RANKS[$player['donor_rank']];
			$donorBanner = self::STORE_RANK_BANNERS[$player['donor_rank']];
			$player['userBannerDonator'] = sprintf(
				'<em class="userBanner %s">%s</em>',
				$donorBanner,
				$donorName
			);
		} else {
			$player['userBannerDonator'] = '';
		}
	}

	private function isServerOffline(int $lastUpdate): bool {
		return $lastUpdate < (time() - self::OFFLINE_THRESHOLD);
	}

	public function actionDumpjson() {
		// Add CORS headers
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, OPTIONS');
		header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
		header('Content-Type: application/json');

		// Handle preflight OPTIONS request
		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
			http_response_code(200);
			exit();
		}

		$dbCore = self::getCoreDbInstance();

		if (!$dbCore) {
			return $this->error(\XF::phrase('database_connection_failed'));
		}

		$servers = $dbCore->fetchAll('SELECT * FROM servers');

		echo json_encode($servers);
		die();
	}

	public static function getActivityDetails(array $activities): array {
		return [
			[
				'description' => 'Viewing',
				'title' => 'server list',
				'url' => '/gl/servers'
			]
		];
	}
}