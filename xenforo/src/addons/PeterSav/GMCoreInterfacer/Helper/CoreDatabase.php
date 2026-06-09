<?php

namespace PeterSav\GModInterface\Helper;

class CoreDatabase {
	protected static ?\XF\Db\AbstractAdapter $coreDb = null;
	protected static ?\XF\Mvc\Entity\Manager $coreEntityManager = null;

	public static function getCoreDbInstance(): ?\XF\Db\AbstractAdapter {
		if (self::$coreDb === null) {
			try {
				/** @var \XF\App $app */
				$app = \XF::app();
				$options = $app->options();

				self::$coreDb = new \XF\Db\Mysqli\Adapter([
					'host' => $options->gmod_interfacer_db_host,
					'port' => intval($options->gmod_interfacer_db_port),
					'username' => $options->gmod_interfacer_db_username,
					'password' => $options->gmod_interfacer_db_password,
					'dbname' => $options->gmod_interfacer_db_name
				], true);

				self::$coreDb->connect();
			} catch (\XF\Db\Exception $e) {
				self::$coreDb = null;
				\XF::logError("Database connection error: " . $e->getMessage());
			}
		}

		return self::$coreDb;
	}

	public static function getCoreEntityManager(): ?\XF\Mvc\Entity\Manager {
		if (self::$coreEntityManager === null) {
			$db = self::getCoreDbInstance();
			if ($db === null) {
				return null;
			}
			$em = clone \XF::em();
			$reflection = new \ReflectionObject($em);
			$dbProperty = $reflection->getProperty('db');
			$dbProperty->setAccessible(true);
			$dbProperty->setValue($em, $db);
			self::$coreEntityManager = $em;
		}
		return self::$coreEntityManager;
	}
}