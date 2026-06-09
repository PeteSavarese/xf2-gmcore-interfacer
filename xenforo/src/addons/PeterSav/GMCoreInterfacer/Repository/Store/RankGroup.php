<?php

namespace PeterSav\GModInterface\Repository\Store;

use AllowDynamicProperties;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Repository;

#[AllowDynamicProperties]
class RankGroup extends Repository {
	public function __construct(Manager $em, $identifier) {
		$em = clone $em;

		parent::__construct($em, $identifier);
	}

	public function findAllOrdered(): Finder {
		return $this->finder('PeterSav\\GModInterface:Store\\RankGroup')
			->order('rank_priority');
	}

	/**
	 * @param int $id
	 * @return RankGroup|null
	 */
	public function getByUpgradeId(int $id) {
		return $this->em->find('PeterSav\\GModInterface:Store\\RankGroup', $id);
	}
}
