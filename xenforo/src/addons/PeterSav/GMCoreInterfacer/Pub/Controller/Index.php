<?php

namespace PeterSav\GModInterface\Pub\Controller;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use PeterSav\GModInterface\Service\HLogs\Provider as HLogsProvider;
use XF\Mvc\ParameterBag;

class Index extends AbstractController {
	public function actionBans() {
		$dbCore = self::getCoreDbInstance();

		$selectBans = $dbCore->fetchAll("
          SELECT
              b.id,
              b.name,
              b.banned_on,
              b.steamid,
              b.reason,
              b.unban_time,
              b.banned_by,
              b.server          AS server_id,
              s.niceName        AS server_name,
              b.status          AS ban_status
          FROM bans b
          LEFT JOIN servers s ON b.server = s.id
          WHERE (b.unban_time > UNIX_TIMESTAMP() OR b.unban_time = 0)
            AND b.status = 1
            AND b.void = 'N'
          ORDER BY b.banned_on DESC
      ");

		$bansData = [];
		foreach ($selectBans as $ban) {
			$bannedDate = $ban['banned_on'];
			$expiresDate = $ban['unban_time'] == 0 ? 0 : $ban['unban_time'];

			// If Global then display Global, else use niceName left joined
			$serverDisplay = ($ban['server_id'] === 'Global')
				? 'Global'
				: ($ban['server_name'] ?: 'Unknown');

			$bansData[] = [
				$ban['name'] ?: 'Unknown',
				$ban['steamid'],
				$bannedDate,
				$expiresDate,
				$ban['reason'],
				$ban['banned_by'] ?: 'Unknown',
				$serverDisplay,
				$ban['ban_status']
			];
		}

		return $this->view('', 'gmod_bans', [
			'bansData' => json_encode($bansData)
		]);
	}

	public function actionHlogsSearch() {
		$user = \XF::visitor();
		$canView = $user->hasPermission('gmodUserPerms', 'view_hlogs');

		if (!$canView) {
			return $this->noPermission();
		}

		/** @var HLogsProvider $provider */
		$provider = $this->service('PeterSav\\GModInterface:HLogs\\Provider');

		$query = trim($this->filter('q', 'str'));
		$steamId = trim($this->filter('steamid', 'str'));
		if ($query === '' && $steamId !== '') {
			$query = $steamId;
		}

		if ($query !== '' && $provider->looksLikeSteamId($query)) {
			return $this->redirect("gl/hlogs/results?steamid={$query}");
		}

		$viewParams = $provider->getSearchViewParams($query);

		return $this->view('', 'gmod_hlogs_search', $viewParams);
	}

	public function actionHlogsResults(ParameterBag $params) {
		$user = \XF::visitor();

		if (!$user->hasPermission("gmodUserPerms", "view_hlogs")) {
			return $this->noPermission();
		}

		$searchQuery = trim($this->filter('q', 'str'));

		$steamIdInput = trim($this->filter('steamid', 'str'));
		if ($steamIdInput === '') {
			$steamIdInput = $searchQuery;
		}

		if ($steamIdInput === '') {
			return $this->error('A SteamID was not provided');
		}

		/** @var HLogsProvider $provider */
		$provider = $this->service('PeterSav\\GModInterface:HLogs\\Provider');
		$result = $provider->getResultsViewParams($steamIdInput, $user);

		if (!($result['ok'] ?? false)) {
			return $this->error($result['error'] ?? 'Unable to load history logs');
		}

		$viewParams = $result['viewParams'] ?? [];
		$viewParams['searchQuery'] = $searchQuery;

		return $this->view('', 'gmod_hlogs_results', $viewParams);
	}

	public function actionIpRequesthistory(ParameterBag $params) {
		$visitor = \XF::visitor();

		if (!$visitor->hasPermission('general', 'viewIps')) {
			return $this->noPermission();
		}

		$dbCore = self::getCoreDbInstance();
		$dbForums = \XF::db();

		$fetchIPHistorySQL = $dbCore->fetchRow(
			'SELECT ip_history FROM player_data WHERE steamid64 = ?',
			$params->message_id
		);

		$ipHistoryRaw = (is_array($fetchIPHistorySQL) && array_key_exists('ip_history', $fetchIPHistorySQL))
			? $fetchIPHistorySQL['ip_history']
			: null;

		$ipHistory = [];
		if (is_string($ipHistoryRaw) && $ipHistoryRaw !== '') {
			$decoded = json_decode($ipHistoryRaw, true);

			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$ipHistory = $decoded;
			}
		}

		$dbForums->insert("gmod_hlogs_ip_request_log", [
			"user_id" => $visitor->user_id,
			"ip_address" => \XF\Util\Ip::stringToBinary(\XF::app()->request()->getIp()),
			"request_time" => time(),
			"requested_steamid" => $params->message_id
		]);

		$viewParams = array(
			'ipHistory' => $ipHistory,
		);

		return $this->view('', 'gmod_hlogs_iphistory', $viewParams);
	}

	public function actionNotesAddpopup(ParameterBag $params) {
		$user = \XF::visitor();
		$canView = $user->hasPermission('gmodUserPerms', 'view_hlogs');

		if (!$canView) {
			return $this->noPermission();
		}

		$disabledToolbar = [];
		$disabledToolbar[] = '_image';
		$disabledToolbar[] = '_extended';
		$disabledToolbar[] = '_align';
		$disabledToolbar[] = '_indent';
		$disabledToolbar[] = '_list';
		$disabledToolbar[] = '_media';
		$disabledToolbar[] = '_block';

		$viewParams = array(
			'ignoredButtons' => $disabledToolbar,
			'steamId' => $params->message_id
		);

		return $this->view('', 'gmod_hlogs_addnote', $viewParams);
	}

	public function actionNotesAdd(ParameterBag $params) {
		$this->assertPostOnly();

		$user = \XF::visitor();
		$canView = $user->hasPermission('gmodUserPerms', 'view_hlogs');

		if (!$canView) {
			return $this->noPermission();
		}

		$dbCore = self::getCoreDbInstance();
		$steamid = $params->message_id;
		$note = $this->filter([
			'note_html' => 'str',
			'note' => 'str',
			'visibility' => 'str',
		]);

		// If form was submitted with BBCode enabled
		if (!$note["note_html"]) {
			$note["note_html"] = \XF::app()->bbCode()->render(
				$note["note"],
				'editorHtml',
				'html',
				null,
				[]
			);
		}

		if (!isset($note["note_html"]) or $note["note_html"] == "<p></p>" or !isset($note["visibility"]) or $note["visibility"] == "") {
			return $this->error("There are required fields that are blank/missing.");
		}

		if (!isset($steamid) or $steamid == "") {
			return $this->error("Invalid SteamID in parameter bag.");
		}

		if (strlen(strip_tags($note["note_html"])) > 1000) {
			return $this->error("Your note may not be longer than 1000 characters.");
		}

		$dbCore->insert("player_notes", [
			"note" => $note["note_html"],
			"sidfor" => $steamid,
			"by" => $user->user_id,
			"added" => time(),
			"visibility" => $note["visibility"]
		]);

		return $this->redirect("gl/hlogs/results?steamid={$steamid}");
	}

	public function actionVoidAddpopup(ParameterBag $params) {
		$user = \XF::visitor();
		$banId = $params->message_id;
		$canView = $user->hasPermission('gmodUserPerms', 'view_hlogs');

		if (!$canView) {
			return $this->noPermission();
		}

		$disabledToolbar = [];
		$disabledToolbar[] = '_image';
		$disabledToolbar[] = '_extended';
		$disabledToolbar[] = '_align';
		$disabledToolbar[] = '_indent';
		$disabledToolbar[] = '_list';
		$disabledToolbar[] = '_media';
		$disabledToolbar[] = '_block';

		$viewParams = array(
			'ignoredButtons' => $disabledToolbar,
			'banId' => $banId
		);

		return $this->view('', 'gmod_hlogs_void_popup', $viewParams);
	}

	public function actionVoidAdd(ParameterBag $params) {
		$this->assertPostOnly();

		$dbCore = self::getCoreDbInstance();

		$user = \XF::visitor();
		$banId = $params->message_id;
		$canView = $user->hasPermission('gmodUserPerms', 'view_hlogs');

		if (!$canView) {
			return $this->noPermission();
		}

		$steamid = $params->message_id;
		$voidReason = $this->filter([
			'void_reason' => 'str',
		]);

		$voidReasonText = $voidReason["void_reason"];

		if (!isset($voidReason["void_reason"]) || trim($voidReason["void_reason"]) == "" || trim(strip_tags($voidReasonText)) == "") {
			return $this->error("There are required fields that are blank/missing.");
		}

		if (!isset($steamid) || $steamid == "") {
			return $this->error("Invalid SteamID in parameter bag.");
		}

		if (strlen(strip_tags($voidReasonText)) > 1000) {
			return $this->error("Your note may not be longer than 1000 characters.");
		}

		$voidStatus = $dbCore->fetchRow("SELECT steamid, void FROM bans WHERE id = ?", $banId);

		if ($voidStatus['void'] == "N") {
			$dbCore->update("bans", [
				"void" => "Y",
				"void_user" => $user->user_id,
				"void_reason" => $voidReasonText,
			], "id = ?", $banId);
		} else {
			return $this->error("The ban is already voided.");
		}

		return $this->redirect("gl/hlogs/results?steamid={$voidStatus['steamid']}");
	}

	public function actionVoidRemove(ParameterBag $params) {
		$dbCore = self::getCoreDbInstance();

		$user = \XF::visitor();
		$banId = $params->message_id;
		$canView = $user->hasPermission('gmodUserPerms', 'view_hlogs');

		if (!$canView) {
			return $this->noPermission();
		}

		$voidStatus = $dbCore->fetchRow("SELECT steamid, void FROM bans WHERE id = ?", $banId);

		if ($voidStatus['void'] == "Y") {
			$dbCore->update("bans", [
				"void" => "N",
			], "id = ?", $banId);
		} else {
			return $this->error("The ban is currently not voided.");
		}

		return $this->redirect("gl/hlogs/results?steamid={$voidStatus['steamid']}");
	}
}
