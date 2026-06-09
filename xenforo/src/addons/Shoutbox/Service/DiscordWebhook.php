<?php

namespace Shoutbox\Service;

use XF;
use XF\Entity\User;

/**
 * Service for sending and deleting messages via Discord webhook.
 *
 * Enables XenForo shoutbox → Discord synchronization by posting messages
 * to a configured Discord channel webhook and deleting them when needed.
 *
 * Configuration via environment variable: DISCORD_SHOUTBOX_WEBHOOK_URL
 */
class DiscordWebhook
{
	/**
	 * @var string Discord webhook URL for sending messages
	 */
	protected string $webhookUrl;

	/**
	 * Initialize the Discord webhook service.
	 *
	 * @param string|null $webhookUrl Optional explicit webhook URL (defaults to DISCORD_SHOUTBOX_WEBHOOK_URL env var)
	 */
	public function __construct(?string $webhookUrl = null)
	{
		$envUrl = getenv('DISCORD_SHOUTBOX_WEBHOOK_URL');
		$this->webhookUrl = $webhookUrl ?: (is_string($envUrl) ? $envUrl : '');
		$this->webhookUrl = trim($this->webhookUrl);
	}

	/**
	 * Send a shoutbox message to Discord via webhook.
	 *
	 * Posts the message using the XenForo user's avatar and username.
	 * Message is truncated to 2000 characters (Discord's limit).
	 *
	 * @param User $user The XenForo user posting the message
	 * @param string $message The message text to send
	 * @return string|null The Discord message ID if successful, null on failure
	 */
	public function sendMessage(User $user, string $message): ?string
	{
		if ($this->webhookUrl === '') {
			XF::logError('Discord webhook URL not configured (DISCORD_SHOUTBOX_WEBHOOK_URL).');
			return null;
		}

		$discordMessage = substr($message, 0, 2000);

		$payload = [
			'username' => $user->username,
			'avatar_url' => XF::app()->request()->getHostUrl() . $user->getAvatarUrl('l'),
			'content' => $discordMessage,
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->webhookUrl);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode >= 200 && $httpCode < 300) {
			$data = json_decode($response, true);
			return $data['id'] ?? null;
		}

		XF::logError("Discord webhook failed: HTTP $httpCode\nPayload: " . print_r($payload, true) . "\nResponse: " . $response);
		return null;
	}

	/**
	 * Delete a message from Discord via webhook.
	 *
	 * Used when a shoutbox message is deleted from XenForo to maintain sync.
	 * Requires the Discord message ID that was returned from sendMessage().
	 *
	 * @param string $discordMessageId The Discord message snowflake ID to delete
	 * @return bool True if deletion succeeded, false on failure
	 */
	public function deleteMessage(string $discordMessageId): bool
	{
		if ($this->webhookUrl === '') {
			XF::logError('Discord webhook URL not configured (DISCORD_SHOUTBOX_WEBHOOK_URL).');
			return false;
		}

		$deleteUrl = preg_replace('/\?wait=true$/', '', $this->webhookUrl);
		$deleteUrl .= '/messages/' . $discordMessageId;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $deleteUrl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode >= 200 && $httpCode < 300) {
			return true;
		}

		XF::logError("Discord delete message failed: HTTP $httpCode\nMessage ID: $discordMessageId\nResponse: " . $response);
		return false;
	}
}
