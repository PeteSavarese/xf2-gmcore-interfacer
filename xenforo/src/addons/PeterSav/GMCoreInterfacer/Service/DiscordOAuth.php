<?php

namespace PeterSav\GModInterface\Service;

use XF;

class DiscordOAuth {
	protected const DISCORD_API_BASE = "https://discord.com/api/v10";

	protected XF\Options $options;

	public function __construct() {
		$this->options = XF::app()->options();
	}

	protected static function makeBaseApiRequest(string $endpoint, array $headers = [], ?string $postData = null, string $method = 'POST'): ?array {
		$ch = curl_init();
		$curlOptions = [
			CURLOPT_URL => self::DISCORD_API_BASE . $endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
		];

		if ($postData !== null) {
			$curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
			$curlOptions[CURLOPT_POSTFIELDS] = $postData;
		}

		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);
		curl_close($ch);

		return json_decode($response, true);
	}

	public function exchangeCodeForToken(string $code) {
		$redirectUri = XF::app()->router('public')->buildLink('canonical:discord/process');

		$body = http_build_query([
			'client_id' => $this->options->gmod_interfacer_discord_bot_client_id,
			'client_secret' => $this->options->gmod_interfacer_discord_bot_client_secret,
			'grant_type' => 'authorization_code',
			'code' => $code,
			'redirect_uri' => $redirectUri,
		]);

		return self::makeBaseApiRequest(
			'/oauth2/token',
			['Content-Type: application/x-www-form-urlencoded'],
			$body
		);
	}

	public function getDiscordMemberFromAccessToken(string $accessToken) {
		return self::makeBaseApiRequest(
			'/users/@me',
			[
				"Authorization: Bearer {$accessToken}",
				"Accept: application/json"
			]
		);
	}

	public function addUserToGuild(string $userId, string $accessToken) {
		$guildId = $this->options->gmod_interfacer_discord_server_guild;
		$botToken = $this->options->gmod_interfacer_discord_bot_token;

		$body = json_encode([
			'access_token' => $accessToken,
		]);

		return self::makeBaseApiRequest(
			"/guilds/{$guildId}/members/{$userId}",
			[
				"Authorization: Bot {$botToken}",
				"Content-Type: application/json"
			],
			$body,
			'PUT'
		);
	}
}