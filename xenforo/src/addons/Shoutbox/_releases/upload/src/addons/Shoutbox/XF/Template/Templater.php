<?php

namespace Shoutbox\XF\Template;

use DateTime;
use DateTimeZone;
use XF;

/**
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpUndefinedMethodInspection
 */
class Templater extends XFCP_Templater
{
	public function addFunctions(array $functions)
	{
		$functions['shoutbox_date_dynamic'] = 'fnShoutboxDateDynamic';
		$functions['shoutbox_is_banned'] = 'fnShoutboxIsBanned';
		$functions['shoutbox_mediaify'] = 'fnShoutboxMediaify';

		return parent::addFunctions($functions);
	}

	public function fnShoutboxMediaify($templater, &$escape, $string)
	{
		// Return raw BB code for the bb_code() renderer; do not escape.
		$escape = false;
		if (!is_string($string) || $string === '') {
			return '';
		}

		$discordAttachmentLabelForUrl = function (string $url): ?string {
			$host = parse_url($url, PHP_URL_HOST);
			$path = parse_url($url, PHP_URL_PATH);
			if (!$host || !$path) {
				return null;
			}

			$isDiscord = (bool)preg_match('~(^|\.)discordapp\.com$|(^|\.)discordapp\.net$~i', $host);
			if (!$isDiscord || stripos($path, '/attachments/') === false) {
				return null;
			}

			$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'avi'], true)) {
				return '(Attached video)';
			}
			if (in_array($ext, ['mp3', 'ogg', 'wav', 'flac', 'm4a', 'aac'], true)) {
				return '(Attached audio)';
			}
			if ($ext === '' || in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'], true)) {
				return '(Attached photo)';
			}

			return '(Attached file)';
		};

		// Convert markdown-style links first so URLs in parentheses are still clickable.
		// We preserve the URL as the visible text, except for Discord attachments
		// which become labeled links (photo/video/audio/file).
		$string = preg_replace_callback(
			'~\[(?P<text>[^\]\r\n]+)\]\((?P<url>https?://[^\)\s]+)\)~i',
			function (array $match)
			use ($discordAttachmentLabelForUrl) {
				$urlWithPunctuation = trim($match['url']);
				$url = rtrim($urlWithPunctuation, ".,!?;:)]");
				$trailing = substr($urlWithPunctuation, strlen($url));

				$label = $discordAttachmentLabelForUrl($url);
				if ($label !== null) {
					return "[URL={$url}]{$label}[/URL]" . $trailing;
				}

				return "[URL]{$url}[/URL]" . $trailing;
			},
			$string
		);

		// Auto-wrap bare URLs into BB code so the shoutbox reliably hyperlinks them.
		// We preserve the URL as the visible text, except for Discord attachments
		// which become labeled links (photo/video/audio/file).
		//
		// We only match URLs that are preceded by whitespace or start-of-string,
		// which avoids double-wrapping URLs already inside BB code tags.
		$string = preg_replace_callback(
			'~(^|\s)(https?://[^\s<]+)~i',
			function (array $match)
			use ($discordAttachmentLabelForUrl) {
				$prefix = $match[1];
				$urlWithPunctuation = $match[2];

				$url = rtrim($urlWithPunctuation, ".,!?;:)]");
				$trailing = substr($urlWithPunctuation, strlen($url));

				$label = $discordAttachmentLabelForUrl($url);
				if ($label !== null) {
					return $prefix . "[URL={$url}]{$label}[/URL]" . $trailing;
				}

				return $prefix . "[URL]{$url}[/URL]" . $trailing;
			},
			$string
		);

		return $string;
	}

	protected static ?array $shoutboxActiveBanMap = null;

	protected function getShoutboxActiveBanMap(): array
	{
		if (self::$shoutboxActiveBanMap !== null) {
			return self::$shoutboxActiveBanMap;
		}

		$now = date('Y-m-d H:i:s');
		$ids = XF::db()->fetchAllColumn(
			"SELECT user_id FROM shoutbox_ban WHERE expires_on IS NULL OR expires_on > ?",
			$now
		);

		$map = [];
		foreach ($ids as $id) {
			$map[(int)$id] = true;
		}

		self::$shoutboxActiveBanMap = $map;
		return $map;
	}

	public function fnShoutboxIsBanned($templater, &$escape, $userId)
	{
		$userId = (int)$userId;
		if (!$userId) {
			return false;
		}

		$map = $this->getShoutboxActiveBanMap();
		return !empty($map[$userId]);
	}

	public function fnShoutboxDateDynamic($templater, &$escape, $dateTime, array $attributes = [])
	{
		if ($dateTime === null || $dateTime === '') {
			$escape = false;
			return '';
		}

		if ($dateTime instanceof DateTime) {
			return $this->fnDateDynamic($templater, $escape, $dateTime, $attributes);
		}

		if (is_int($dateTime) || (is_string($dateTime) && ctype_digit($dateTime))) {
			return $this->fnDateDynamic($templater, $escape, (int)$dateTime, $attributes);
		}

		$ts = null;

		// MariaDB DATETIME/TIMESTAMP values commonly come through as: YYYY-MM-DD HH:MM:SS
		// Treat as UTC (matching XenForo's typical storage expectations) and convert to unix timestamp.
		if (is_string($dateTime)) {
			$dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime, new DateTimeZone('UTC'));
			if ($dt) {
				$ts = $dt->getTimestamp();
			} else {
				$tmp = strtotime($dateTime);
				if ($tmp !== false) {
					$ts = (int)$tmp;
				}
			}
		}

		if ($ts === null) {
			$escape = false;
			return '';
		}

		// Use XenForo's core fnDateDynamic renderer.
		return $this->fnDateDynamic($templater, $escape, $ts, $attributes);
	}
}
