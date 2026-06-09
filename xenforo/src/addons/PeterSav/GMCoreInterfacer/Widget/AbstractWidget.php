<?php

namespace PeterSav\GModInterface\Widget;

use XF\Widget\AbstractWidget as XFAbstractWidget;

abstract class AbstractWidget extends XFAbstractWidget {
	protected static $dbCore = null;

	protected static function getCoreDbInstance() {
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
				\XF::logError("GModInterface DB connection failed: " . $e->getMessage());
			}
		}

		return self::$dbCore;
	}
}
