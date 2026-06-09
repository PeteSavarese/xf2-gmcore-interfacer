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

		// Check if ban has expired
		if ($ban->expires_on && strtotime($ban->expires_on) < XF::$time) {
			// Ban has expired, delete it
			$ban->delete();
			return false;
		}

		return true;
	}
}
