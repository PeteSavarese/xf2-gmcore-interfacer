<?php

namespace Shoutbox\Repository;

use Shoutbox\Entity\ShoutboxBan as ShoutboxBanEntity;
use XF;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class ShoutboxBan extends Repository
{
	public function findActiveBans(): Finder
	{
		$now = date('Y-m-d H:i:s');
		return $this->finder('Shoutbox:ShoutboxBan')
			->with(['User', 'BannedBy'])
			->whereOr([
				['expires_on', '=', null],
				['expires_on', '>', $now]
			])
			->order('banned_on', 'DESC');
	}

	public function getActiveBanForUserId(int $userId): ?ShoutboxBanEntity
	{
		if ($userId <= 0) {
			return null;
		}

		$now = date('Y-m-d H:i:s');

		/** @var ShoutboxBanEntity|null $ban */
		$ban = $this->finder('Shoutbox:ShoutboxBan')
			->with(['User', 'BannedBy'])
			->where('user_id', $userId)
			->whereOr([
				['expires_on', '=', null],
				['expires_on', '>', $now]
			])
			->fetchOne();

		return $ban;
	}

	public function getActiveBanForVisitor(): ?ShoutboxBanEntity
	{
		$visitor = XF::visitor();
		return $this->getActiveBanForUserId((int)$visitor->user_id);
	}
}
