<?php

namespace Shoutbox\XF\Entity;

use XF;

class User extends XFCP_User
{
	public function isBannedFromShoutbox()
	{
		if (!$this->user_id) {
			return false;
		}

		$ban = $this->em()->find('Shoutbox:ShoutboxBan', $this->user_id);

		if (!$ban) {
			return false;
		}

		if ($ban->expires_on && strtotime($ban->expires_on) < XF::$time) {
			// Check if banned so we don't always have to wait on CRON job
			$ban->delete();

			return false;
		}

		return true;
	}
}
