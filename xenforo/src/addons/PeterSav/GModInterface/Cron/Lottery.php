<?php

namespace PeterSav\GModInterface\Cron;

class Lottery {
	protected static $dbCore = null;

	public function __construct(\XF\App $app) {
		$this::getCoreDbInstance();
	}

	public static function getCoreDbInstance() {
		if (self::$dbCore === null) {
			try {
				$app = \XF::app();
				$options = $app->options();

				self::$dbCore = new \XF\Db\Mysqli\Adapter([
					'host' => $options->gmod_interfacer_db_host,
					'port' => intval($options->gmod_interfacer_db_port),
					'username' => $options->gmod_interfacer_db_username,
					'password' => $options->gmod_interfacer_db_password,
					'dbname' => $options->gmod_interfacer_db_name
				], true);

				self::$dbCore->connect();
			} catch (\XF\Db\Exception $e) {
				self::$dbCore = null;
				\XF::logError("GModInterface DB connection failed in Lottery cron: " . $e->getMessage());
			}
		}

		return self::$dbCore;
	}

	public static function run() {
		$dbCore = self::getCoreDbInstance();
		if (!$dbCore) {
			\XF::logError("GModInterface Lottery cron: Could not connect to GMod database.");
			return;
		}

		$incrementedMappingCount = 0; // Total tickets count
		$ticketMapping = []; // Player ticket bounds

		$ticketEntries = $dbCore->fetchAll("SELECT * FROM lottery_entries ORDER BY tickets DESC");

		if (count($ticketEntries) === 0) {
			return; // No tickets, nothing to do
		}

		foreach ($ticketEntries as $entry) {
			$ticketMapping[$entry["steamid"]] = [$incrementedMappingCount, $incrementedMappingCount + $entry["tickets"]];
			$incrementedMappingCount += $entry["tickets"];
		}

		$randomTicketNumber = rand(0, $incrementedMappingCount - 1);
		$winnerSteamId = null;

		foreach ($ticketMapping as $steamid => [$min, $max]) {
			if ($randomTicketNumber >= $min && $randomTicketNumber < $max) {
				$winnerSteamId = (string)$steamid;
				break;
			}
		}

		if ($winnerSteamId === null) {
			\XF::logError("GModInterface Lottery cron: No winner found for ticket number {$randomTicketNumber}.");
			return;
		}

		foreach ($ticketEntries as $entry) {
			if ($entry["steamid"] === $winnerSteamId) {
				$jackpot = $incrementedMappingCount * 100;
				$dbCore->insert("lottery_history", [
					"steamid" => $winnerSteamId,
					"player_name" => $entry["player_name"],
					"tickets" => $entry["tickets"],
					"jackpot_won" => $jackpot,
					"date_won" => time()
				]);
				$dbCore->emptyTable("lottery_entries");
				break;
			}
		}
	}
}
