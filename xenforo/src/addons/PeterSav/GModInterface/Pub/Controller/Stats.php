<?php

namespace PeterSav\GModInterface\Pub\Controller;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use PeterSav\GModInterface\Service\Stats\Provider;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Stats extends AbstractController {
	public function actionIndex() {
		$steamId = trim($this->filter('steamid', 'str'));
		$query = trim($this->filter('q', 'str'));

		/** @var Provider $provider */
		$provider = $this->service('PeterSav\\GModInterface:Stats\\Provider');

		if ($steamId === '' && $query !== '' && $this->looksLikeSteamId($query)) {
			$steamId = $query;
			$query = '';
		}

		if ($steamId === '') {
			$visitor = \XF::visitor();
			$mySteamId = null;

			if ($visitor && $visitor->Profile) {
				$mySteamId = $visitor->Profile->connected_accounts['steam'] ?? null;
			}

			$viewParams = $provider->getHubViewParams($mySteamId, $query);

			if ($this->filter('json', 'bool')) {
				header('Content-Type: application/json');
				echo json_encode($viewParams);

				exit;
			}

			return $this->view('', 'gmod_stats_hub', $viewParams);
		}

		$result = $provider->getPlayerViewParams($steamId);

		if (!($result['ok'] ?? false)) {
			$message = $result['error'] ?? 'No player stats found';

			if ($this->filter('json', 'bool')) {
				header('Content-Type: application/json');
				echo json_encode(["errors" => [["code" => "player_stats_not_found", "message" => $message]]]);
				http_response_code(404);

				exit;
			}

			return $this->error($message);
		}

		$viewParams = $result['viewParams'] ?? [];

		if ($this->filter('json', 'bool')) {
			header('Content-Type: application/json');
			echo json_encode($viewParams);

			exit;
		}

		return $this->view('', 'gmod_stats', $viewParams);
	}

	private function looksLikeSteamId(string $value): bool {
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
	 * @param \XF\Entity\SessionActivity[] $activities
	 * @return array
	 */
	public static function getActivityDetails(array $activities): array {
		$app = \XF::app();
		$templater = $app->templater();

		$leaderboardUrl = '/gl/stats';

		/** @var \PeterSav\GModInterface\Repository\Stats\Stats $statsRepo */
		$statsRepo = $app->repository('PeterSav\\GModInterface:Stats\\Stats');
		$dbCore = $statsRepo->coreDb();

		$output = [];
		foreach ($activities as $key => $activity) {
			$steamId64 = trim($activity->pluckParam('steamid'));

			if ($steamId64 !== '') {
				$playerName = $dbCore ? $dbCore->fetchOne(
					'SELECT in_game_name FROM player_data WHERE steamid64 = ?',
					$steamId64
				) : null;

				$playerName = is_string($playerName) && $playerName !== '' ? $playerName : $steamId64;

				$output[$key] = [
					'description' => $templater->preEscaped('Viewing TTT stats of'),
					'title' => $playerName,
					'url' => $leaderboardUrl . '?steamid=' . rawurlencode($steamId64)
				];
			} else {
				$output[$key] = [
					'description' => $templater->preEscaped('Viewing <a href="' . $leaderboardUrl . '">TTT leaderboard</a>'),
					'title' => '',
					'url' => ''
				];
			}
		}

		return $output;
	}

	protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply) {
		// XenForo session activity params come from route params (ParameterBag), not the query string.
		// Stats uses ?steamid={XXXX} to switch to a player view, so need to explicitly persist it
		if ($action === 'Index' && empty($params['steamid'])) {
			$steamId = trim($this->filter('steamid', 'str'));

			if ($steamId !== '') {
				$params['steamid'] = $steamId;
			}
		}

		parent::updateSessionActivity($action, $params, $reply);
	}
}
