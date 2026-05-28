<?php

namespace PeterSav\GModInterface\Service;

use XF;

class DiscordAPI {
	protected const DISCORD_API_BASE = "https://discord.com/api/v10";

	protected XF\Options $options;

	public function __construct() {
		$this->options = XF::app()->options();
	}

	/**
	 * Makes an authenticated request to the Discord API for the configured guild.
	 */
	protected function makeApiRequest(string $endpoint, array $extraHeaders = [], ?string $postData = null, ?string $method = null, ?string $auditReason = null, ?bool $withoutGuild = false): array {
		$headers = [
			"Authorization: Bot {$this->options->gmod_interfacer_discord_bot_token}",
			"Accept: application/json",
			...$extraHeaders,
		];

		if ($auditReason) {
			$headers[] = "X-Audit-Log-Reason: " . rawurlencode($auditReason);
		}

		$ch = curl_init();
		$curlOptions = [
			CURLOPT_URL => sprintf(
				'%s%s%s',
				self::DISCORD_API_BASE,
				$withoutGuild ? '/' : "/guilds/{$this->options->gmod_interfacer_discord_server_guild}/",
				ltrim($endpoint, '/')
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HEADER => true, // Include headers in response to get status code
		];

		if ($method) {
			$curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
		}

		if ($postData !== null) {
			$curlOptions[CURLOPT_POST] = true;
			$curlOptions[CURLOPT_POSTFIELDS] = $postData;
		}

		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);

		$body = substr($response, $headerSize);

		// Handle successful responses that may have empty bodies (like 204 No Content)
		$data = null;
		if (!empty($body)) {
			$data = json_decode($body, true);
		}

		return [
			'status_code' => $httpCode,
			'data' => $data,
			'success' => $httpCode >= 200 && $httpCode < 300
		];
	}

	public function getUser(string $discordId) {
		$result = $this->makeApiRequest("users/{$discordId}", [], null, null, null, true);
		return $result['success'] ? $result['data'] : null;
	}

	public function getGuildMember(string $memberId) {
		$result = $this->makeApiRequest("members/{$memberId}");
		return $result['success'] ? $result['data'] : null;
	}

	public function getGuildRolesMap() {
		$result = $this->makeApiRequest("roles");
		$roles = $result['success'] ? $result['data'] : null;

		if (!is_array($roles)) {
			return [];
		}

		$map = [];
		foreach ($roles as $r) {
			$r['color_hex'] = sprintf("%06X", $r['color'] ?? 0);
			$map[$r['id']] = $r;
		}

		return $map;
	}

	public function getGuildRole($roleId) {
		$result = $this->makeApiRequest("roles/{$roleId}");
		return $result['success'] ? $result['data'] : null;
	}

	public function addMemberRole(string $memberId, string $roleId, ?string $auditReason = null): array {
		return $this->makeApiRequest(
			"members/{$memberId}/roles/{$roleId}",
			[],
			'', // Empty body for PUT request
			'PUT',
			$auditReason ?: "Role added via XenForo integration"
		);
	}

	public function removeMemberRole(string $memberId, string $roleId, ?string $auditReason = null): array {
		return $this->makeApiRequest(
			"members/{$memberId}/roles/{$roleId}",
			[],
			null,
			'DELETE',
			$auditReason ?: "Role removed via XenForo integration"
		);
	}
}
