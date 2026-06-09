<?php

namespace PeterSav\GModInterface\Pub\Controller;

use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Lottery extends AbstractController {
	public function actionIndex(ParameterBag $params) {
		return "";
	}

	public function actionDumpjson() {
		$dbCore = self::getCoreDbInstance();

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

		$TICKET_WORTH = 100; // 1 ticket equals how many points?

		$globalDump = array();
		$globalDump["current_jackpot"] = 0;
		$globalDump["entries"] = $dbCore->fetchAll("SELECT * FROM lottery_entries ORDER BY tickets DESC");
		$globalDump["history_winners"] = $dbCore->fetchAll("SELECT * FROM lottery_history ORDER BY date_won DESC LIMIT 28");

		foreach ($globalDump["entries"] as $key => $entry) {
			$globalDump["current_jackpot"] += $entry["tickets"] * $TICKET_WORTH;
		}

		echo json_encode($globalDump);
		die();
	}
}
