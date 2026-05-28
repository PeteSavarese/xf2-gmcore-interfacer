<?php

namespace PeterSav\GModInterface\Repository;

use PeterSav\GModInterface\Entity\DiscordLink as DiscordLinkEntity;
use PeterSav\GModInterface\Pub\Controller\AbstractController;
use XF;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

class DiscordLink extends Repository {
	public function __construct(Manager $em, $identifier) {
		$em = clone $em;
		$reflection = new \ReflectionObject($em);
		$dbProperty = $reflection->getProperty('db');
		$dbProperty->setAccessible(true);
		$dbProperty->setValue($em, AbstractController::getCoreDbInstance());
		parent::__construct($em, $identifier);
	}
}