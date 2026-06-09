<?php

namespace PeterSav\GModInterface\XF\Template;

class Templater extends XFCP_Templater {
	public function addDefaultHandlers()
	{
		parent::addDefaultHandlers();

		try
		{
			$config = $this->app->config();
			$this->addDefaultParam('environment', $config['environment'] ?? '');
		}
		catch (\Throwable $e)
		{
			// ignore
		}
	}

	public function addFunctions(array $functions) {
        $functions["get_env"] = "fnGetEnv";
		$functions["user_to_steamid64"] = "fnUserToSteamId64";
		$functions["user_to_gmod_ign"] = "fnUserToGModInGameName";
		$functions["user_to_discord_tag"] = "fnUserToDiscordTag";
		$functions["userid_to_user"] = "fnUserIdToUser";
		$functions["strtolower"] = "fnStrToLower";
		$functions["store_id_to_groupinfo"] = "fnStoreIdToGroupInfo";
		$functions["remove_trailing_gmod_map"] = "fnRemoveTrailingGModMap";
		$functions["round"] = "fnRound";
		$functions["minutes_to_time"] = "fnMinutesToTime";
		$functions["format_date_string"] = "fnFormatDateString";
		$functions["get_store_rank_banner"] = "fnGetStoreRankBanner";
		$functions["discord_guild_role"] = "fnDiscordGuildRole";
		$functions["format_discord_role_bg_color"] = "fnFormatDiscordRoleBackgroundColor";
		$functions["format_discord_role_fg_color"] = "fnFormatDiscordRoleForegroundColor";
		$functions["timestamp_format"] = "fnTimestampFormat";

		return parent::addFunctions($functions);
	}

    public function fnGetEnv($templater, &$escape, $key) {
        return getenv($key);
    }

	public function fnUserToSteamId64($templater, &$escape, $user) {
		if (!$user) {
			$user = \XF::visitor();
		}

		if (!$user->user_id || !$user->Profile) {
			return '';
		}

		return $user->Profile->connected_accounts['steam'] ?? '';
	}

	/** Get GMod in-game name for a XenForo user
	 * Falls back to XenForo username if no linked Steam account or no in-game name found
	 *
	 * TODO: This is realllllly hacky. Need to move rest of this stuff to XF Entity
	 * to also be used for hlogs then.
	 */
	public function fnUserToGModInGameName($templater, &$escape, $user) {
		if (!$user) {
			$user = \XF::visitor();
		}

		if (!$user || !$user->user_id) {
			return '';
		}

		$steamId64 = null;
		if ($user->Profile) {
			$steamId64 = $user->Profile->connected_accounts['steam'] ?? null;
		}

		if (!is_string($steamId64) || $steamId64 === '') {
			return $user->username;
		}

		try {
			$dbCore = \PeterSav\GModInterface\Helper\CoreDatabase::getCoreDbInstance();
			$playerName = $dbCore ? $dbCore->fetchOne(
				'SELECT in_game_name FROM player_data WHERE steamid64 = ?',
				$steamId64
			) : null;

			if (is_string($playerName) && $playerName !== '') {
				return $playerName;
			}
		} catch (\Throwable $e) {
			// ignore and fall back
		}

		return $user->username;
	}

	/*
	* TODO: Actually make this work. Broke since we don't store the discord user tag
	*/
	public function fnUserToDiscordTag($templater, &$escape, $user) {
	}

	public function fnUserIdToUser($templater, &$escape, $userId) {
		$finder = \XF::finder("XF:User");
		$userEntity = $finder->where("user_id", $userId)->fetchOne();

		return $userEntity;
	}

	public function fnStrToLower($templater, &$escape, $string) {
		return strtolower($string);
	}

	public function fnRound($templater, &$escape, $string, $percision) {
		return round($string, $percision ?? 1);
	}

	public function fnStoreIdToGroupInfo($templater, &$escape, $groupId) {
		$db = \XF::db();

		$groupRow = $db->fetchRow("SELECT * FROM gmod_donation_groups WHERE `upgrade_id` = ? ", $groupId);
		$groupRow["nice_title"] = explode("<br>", $groupRow["title"])[0]; // Hacky way to not include the subnotes of "includes everything ..."

		return $groupRow;
	}

	public function fnMinutesToTime($templater, &$escape, $minutes) {
		// Nice format total player time
		$d = floor($minutes / 1440);
		$h = floor(($minutes - $d * 1440) / 60);
		$m = $minutes - ($d * 1440) - ($h * 60);

		return $d . "d, " . $h . "h, and " . $m . "m";
	}

	public function fnRemoveTrailingGModMap($templated, &$escape, $mapName) {
		return preg_replace("/_ahg(_v\d+)*/", "", $mapName);
	}

	public function fnFormatDateString($templater, &$escape, $dateString, $format = 'M j, Y') {
		try {
			$timestamp = strtotime($dateString);

			if ($timestamp === false) {
				return $dateString;
			}

			return date($format, $timestamp);
		} catch (\Exception $e) {
			return $dateString;
		}
	}


	/**
	 * Format a DB datetime (string) or unix timestamp into the visitor's timezone
	 * Defaults to 'm/d/Y g:ia'
	 *
	 * Usage in templates:
	 * - {{ timestamp_format($row.first_joined_date) }}
	 * - {{ timestamp_format($row.first_joined_date, 'm/d/Y g:ia') }}
	 * Assumes DB strings are in UTC unless passed a different source timezone
	 */
	public function fnTimestampFormat($templater, &$escape, $value, $format = 'm/d/Y g:ia', $sourceTimeZone = 'UTC') {
		if ($value === null) {
			return '';
		}

		if (is_int($value)) {
      // Already in UNIX timestamp
			return $this->language->time($value, $format);
		}

		$value = trim((string)$value);
		if ($value === '') {
			return '';
		}

		if (ctype_digit($value)) {
			return $this->language->time((int)$value, $format);
		}

		try {
			$dt = new \DateTimeImmutable($value)->getTimestamp();

      return $this->language->time($dt, format: $format);
		} catch (\Throwable $e) {
			$timestamp = strtotime($value);

			if ($timestamp === false) {
				return $value;
			}

			return $this->language->time($timestamp, $format);
		}
	}


	public function fnGetStoreRankBanner($templater, &$escape, $groupId) {
		$storeHelper = \XF::app()->helper('PeterSav\GModInterface:Store');

		try {
			return $storeHelper->getGroupBanner($groupId);
		} catch (\Exception $e) {
			return '';
		}
	}

	public function fnDiscordGuildRole($templated, &$escape, $roleId) {
		/** @var \PeterSav\GModInterface\Service\DiscordAPI */
		$discordAPI = \XF::app()->service('PeterSav\GModInterface:DiscordAPI');

		try {
			return $discordAPI->getGuildRole($roleId);
		} catch (\Exception $e) {
			return '';
		}
	}

	public function fnFormatDiscordRoleBackgroundColor($templated, &$escape, $color) {
		try {
			if ($color == 0) {
				return '#99AAB5'; // Discord blurple grey (default role color)
			}
			return sprintf("#%06X", $color);
		} catch (\Exception $e) {
			return '';
		}
	}

	public function fnFormatDiscordRoleForegroundColor($templated, &$escape, $color) {
		try {
			if ($color == 0) {
				return '#000000';
			}
			$hexColor = (int)sprintf("%06X", $color);
			$rgb = [
				($hexColor >> 16) & 0xFF,
				($hexColor >> 8) & 0xFF,
				$hexColor & 0xFF
			];
			if (!$rgb || !is_array($rgb) || count($rgb) < 3) {
				return '#000000';
			}
			$luminance = (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255;
			return ($luminance > 0.5) ? '#000000' : '#FFFFFF';
		} catch (\Exception $e) {
			return '';
		}
	}
}
