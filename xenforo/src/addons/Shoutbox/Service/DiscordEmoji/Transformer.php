<?php

namespace Shoutbox\Service\DiscordEmoji;

use Shoutbox\Repository\DiscordEmojiRepository;
use XF\Service\AbstractService;

class Transformer extends AbstractService
{
	/**
	 * :name: -> <:name:ID> or <a:name:ID>.
	 * Unknown names pass through as the original :name: text.
	 */
	public function toDiscord(string $text): string
	{
		if ($text === '') {
			return $text;
		}

		$map = $this->repo()->getMapByName();
		if (!$map) {
			return $text;
		}

		return preg_replace_callback(
			'/:([a-zA-Z0-9_]{2,32}):/',
			function (array $match) use ($map): string {
				$name = $match[1];
				if (!isset($map[$name])) {
					return $match[0];
				}
				$prefix = $map[$name]['animated'] ? 'a' : '';
				return "<{$prefix}:{$name}:{$map[$name]['id']}>";
			},
			$text
		);
	}

	/**
	 * <:name:ID> and <a:name:ID> -> :name:.
	 * Auto-learns: upserts the (name, id, animated) into the cache.
	 */
	public function fromDiscord(string $text): string
	{
		if ($text === '') {
			return $text;
		}

		$repo = $this->repo();

		return preg_replace_callback(
			'/<(a)?:([a-zA-Z0-9_]{2,32}):(\d{15,25})>/',
			function (array $match) use ($repo): string {
				$animated = $match[1] === 'a';
				$name = $match[2];
				$id = $match[3];
				$repo->upsert($name, $id, $animated, 'ingest');
				return ':' . $name . ':';
			},
			$text
		);
	}

	/**
	 * Render-time transform on already-bb_code'd / short_to_emoji'd HTML.
	 * Replaces :name: with an <img> tag from the Discord CDN.
	 * Skips <code>...</code> blocks so code samples stay literal.
	 */
	public function toHtml(string $html): string
	{
		if ($html === '') {
			return $html;
		}

		$map = $this->repo()->getMapByName();
		if (!$map) {
			return $html;
		}

		// Split on <code>...</code> blocks; transform only the non-code segments.
		$parts = preg_split(
			'#(<code\b[^>]*>.*?</code>)#is',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		foreach ($parts as $i => $part) {
			if ($i % 2 === 1) {
				// Odd indices are <code>...</code> blocks: leave untouched.
				continue;
			}
			$parts[$i] = preg_replace_callback(
				'/:([a-zA-Z0-9_]{2,32}):/',
				function (array $match) use ($map): string {
					$name = $match[1];
					if (!isset($map[$name])) {
						return $match[0];
					}
					$id = htmlspecialchars($map[$name]['id'], ENT_QUOTES);
					$ext = $map[$name]['animated'] ? 'gif' : 'webp';
					$alt = htmlspecialchars(':' . $name . ':', ENT_QUOTES);
					return '<img src="https://cdn.discordapp.com/emojis/' . $id . '.' . $ext
						. '" class="shoutbox-discordEmoji" alt="' . $alt . '" title="' . $alt . '" loading="lazy">';
				},
				$parts[$i]
			);
		}

		return implode('', $parts);
	}

	protected function repo(): DiscordEmojiRepository
	{
		/** @var DiscordEmojiRepository $repo */
		$repo = $this->repository('Shoutbox:DiscordEmoji');
		return $repo;
	}
}
