<?php

namespace Shoutbox\Service;

use Exception;
use InvalidArgumentException;
use Shoutbox\Service\DiscordEmoji\Transformer as DiscordEmojiTransformer;
use XF;
use XF\App;
use XF\Entity\User;
use XF\Service\AbstractService;

/**
 * Service for sending, editing, and deleting messages via Discord Bot API.
 *
 * Enables XenForo shoutbox ↔ Discord synchronization by posting messages
 * to a configured Discord channel using the bot token.
 *
 * Configuration via XenForo options:
 * - gmod_interfacer_discord_bot_token: Discord bot token (from GModInterface addon)
 * - shoutbox_discord_channel_id: Discord channel ID for shoutbox messages
 */
class DiscordBotMessenger extends AbstractService
{
	/**
	 * @var string Discord API base URL
	 */
	protected const DISCORD_API_BASE = "https://discord.com/api/v10";

	/**
	 * @var string Discord bot token
	 */
	protected string $botToken;

	/**
	 * @var string Discord channel ID
	 */
	protected string $channelId;

	/**
	 * @var string|null Cached webhook URL
	 */
	protected ?string $webhookUrl = null;

	/**
	 * Initialize the Discord bot messenger service.
	 *
	 * @param App $app XenForo application instance
	 * @param string|null $botToken Optional explicit bot token (defaults to gmod_interfacer_discord_bot_token option)
	 * @param string|null $channelId Optional explicit channel ID (defaults to shoutbox_discord_channel_id option)
	 */
	public function __construct(App $app, ?string $botToken = null, ?string $channelId = null)
	{
		parent::__construct($app);

		$options = $this->app->options();

		$this->botToken = $botToken ?: (string)($options->gmod_interfacer_discord_bot_token ?? '');
		$this->channelId = $channelId ?: (string)($options->shoutbox_discord_channel_id ?? '');

		$this->botToken = trim($this->botToken);
		$this->channelId = trim($this->channelId);
	}

	/**
	 * Make an authenticated request to the Discord API.
	 *
	 * @param string $endpoint API endpoint (e.g., "channels/123/messages")
	 * @param string $method HTTP method (GET, POST, PATCH, DELETE)
	 * @param array|null $data Request body data (will be JSON encoded)
	 * @return array Response with 'success', 'status_code', and 'data' keys
	 */
	protected function makeApiRequest(string $endpoint, string $method = 'GET', ?array $data = null): array
	{
		$client = $this->app->http()->client();
		$url = self::DISCORD_API_BASE . '/' . ltrim($endpoint, '/');

		$options = [
			'headers' => [
				'Authorization' => "Bot {$this->botToken}",
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			],
			'http_errors' => false,
		];

		if ($data !== null) {
			$options['json'] = $data;
		}

		try {
			$response = match (strtoupper($method)) {
				'GET' => $client->get($url, $options),
				'POST' => $client->post($url, $options),
				'PATCH' => $client->patch($url, $options),
				'DELETE' => $client->delete($url, $options),
				default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
			};

			$statusCode = $response->getStatusCode();
			$body = (string)$response->getBody();
			$responseData = $body ? json_decode($body, true) : null;

			return [
				'success' => $statusCode >= 200 && $statusCode < 300,
				'status_code' => $statusCode,
				'data' => $responseData,
			];
		} catch (Exception $e) {
			XF::logException($e);
			return [
				'success' => false,
				'status_code' => 0,
				'data' => ['error' => $e->getMessage()],
			];
		}
	}

	/**
	 * Get or create a webhook for the shoutbox channel.
	 *
	 * Creates a webhook named "XenForo Shoutbox" if one doesn't exist.
	 * Caches the webhook URL in registry for reuse.
	 *
	 * @return string|null Webhook URL with token, or null on failure
	 */
	protected function getOrCreateWebhook(): ?string
	{
		// Check if we have a cached webhook URL in registry (use shorter key)
		$cacheKey = 'sb_wh_' . substr(md5($this->channelId), 0, 16);
		$cachedUrl = $this->app->registry()->get($cacheKey);

		if ($cachedUrl && is_string($cachedUrl)) {
			return $cachedUrl;
		}

		// Get existing webhooks for the channel
		$result = $this->makeApiRequest(
			"channels/{$this->channelId}/webhooks",
			'GET'
		);

		if ($result['success'] && is_array($result['data'])) {
			// Look for existing "XenForo Shoutbox" webhook
			foreach ($result['data'] as $webhook) {
				if (isset($webhook['name']) && $webhook['name'] === 'XenForo Shoutbox' && isset($webhook['id'], $webhook['token'])) {
					$webhookUrl = "https://discord.com/api/webhooks/{$webhook['id']}/{$webhook['token']}";
					$this->app->registry()->set($cacheKey, $webhookUrl);
					return $webhookUrl;
				}
			}
		}

		// Create new webhook
		$createResult = $this->makeApiRequest(
			"channels/{$this->channelId}/webhooks",
			'POST',
			['name' => 'XenForo Shoutbox']
		);

		if ($createResult['success'] && isset($createResult['data']['id'], $createResult['data']['token'])) {
			$webhookUrl = "https://discord.com/api/webhooks/{$createResult['data']['id']}/{$createResult['data']['token']}";
			$this->app->registry()->set($cacheKey, $webhookUrl);
			return $webhookUrl;
		}

		XF::logError("Failed to create Discord webhook for shoutbox: " . print_r($createResult['data'], true));
		return null;
	}

	/**
	 * Send a shoutbox message to Discord.
	 *
	 * Posts the message using the XenForo user's username and avatar.
	 * Message is truncated to 2000 characters (Discord's limit).
	 *
	 * @param User $user The XenForo user posting the message
	 * @param string $message The message text to send
	 * @return string|null The Discord message ID if successful, null on failure
	 */
	public function sendMessage(User $user, string $message): ?string
	{
		if ($this->botToken === '') {
			XF::logError('Discord bot token not configured (gmod_interfacer_discord_bot_token option).');
			return null;
		}

		if ($this->channelId === '') {
			XF::logError('Discord channel ID not configured (shoutbox_discord_channel_id option).');
			return null;
		}

		// Get or create webhook
		$webhookUrl = $this->getOrCreateWebhook();
		if (!$webhookUrl) {
			XF::logError('Failed to get or create Discord webhook for shoutbox.');
			return null;
		}

		$discordMessage = $this->emojiTransformer()->toDiscord($message);
		$discordMessage = substr($discordMessage, 0, 2000);
		$avatarUrl = $this->app->request()->getHostUrl() . $user->getAvatarUrl('l');

		$payload = [
			'content' => $discordMessage,
			'username' => $user->username,
			'avatar_url' => $avatarUrl,
			'allowed_mentions' => [
				'parse' => [], // Don't allow any mentions
			],
		];

		// Use webhook to send message (supports username and avatar customization)
		$client = $this->app->http()->client();
		try {
			$response = $client->post($webhookUrl . '?wait=true', [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'json' => $payload,
				'http_errors' => false,
			]);

			$statusCode = $response->getStatusCode();
			$body = (string)$response->getBody();
			$data = $body ? json_decode($body, true) : null;

			if ($statusCode >= 200 && $statusCode < 300 && isset($data['id'])) {
				return $data['id'];
			}

			$errorDetails = '';
			if (isset($data['message'])) {
				$errorDetails .= "Message: {$data['message']}\n";
			}
			if (isset($data['code'])) {
				$errorDetails .= "Code: {$data['code']}\n";
			}

			XF::logError("Discord webhook send message failed: HTTP {$statusCode}\n{$errorDetails}Webhook URL: " . substr($webhookUrl, 0, 50) . "...\nPayload: " . print_r($payload, true) . "\nFull Response: " . print_r($data, true));
			return null;
		} catch (Exception $e) {
			XF::logException($e);
			return null;
		}
	}

	/**
	 * Edit an existing Discord message.
	 *
	 * Updates the message content while maintaining the original author info.
	 * Uses webhook API to edit the message.
	 *
	 * @param string $discordMessageId The Discord message ID to edit
	 * @param string $newContent The new message content
	 * @return bool True if edit succeeded, false on failure
	 */
	public function editMessage(string $discordMessageId, string $newContent): bool
	{
		if ($this->botToken === '' || $this->channelId === '') {
			XF::logError('Discord bot token or channel ID not configured.');
			return false;
		}

		$webhookUrl = $this->getOrCreateWebhook();
		if (!$webhookUrl) {
			XF::logError('Failed to get webhook for editing Discord message.');
			return false;
		}

		$discordMessage = $this->emojiTransformer()->toDiscord($newContent);
		$discordMessage = substr($discordMessage, 0, 2000);

		$payload = [
			'content' => $discordMessage,
		];

		// Use webhook to edit message
		$client = $this->app->http()->client();
		$url = $webhookUrl . '/messages/' . $discordMessageId;
		try {
			$response = $client->patch($url, [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'json' => $payload,
				'http_errors' => false,
			]);

			$statusCode = $response->getStatusCode();

			if ($statusCode >= 200 && $statusCode < 300) {
				return true;
			}

			$body = (string)$response->getBody();
			$data = $body ? json_decode($body, true) : null;

			$errorDetails = '';
			if (isset($data['message'])) {
				$errorDetails .= "Message: {$data['message']}\n";
			}
			if (isset($data['code'])) {
				$errorDetails .= "Code: {$data['code']}\n";
			}

			XF::logError("Discord webhook edit message failed: HTTP {$statusCode}\n{$errorDetails}Message ID: {$discordMessageId}\nFull Response: " . print_r($data, true));
			return false;
		} catch (Exception $e) {
			XF::logException($e);
			return false;
		}
	}

	/**
	 * Delete a message from Discord.
	 *
	 * Used when a shoutbox message is deleted from XenForo to maintain sync.
	 * Uses webhook API to delete the message.
	 *
	 * @param string $discordMessageId The Discord message ID to delete
	 * @return bool True if deletion succeeded, false on failure
	 */
	public function deleteMessage(string $discordMessageId): bool
	{
		if ($this->botToken === '' || $this->channelId === '') {
			XF::logError('Discord bot token or channel ID not configured.');
			return false;
		}

		$webhookUrl = $this->getOrCreateWebhook();
		if (!$webhookUrl) {
			XF::logError('Failed to get webhook for deleting Discord message.');
			return false;
		}

		// Use webhook to delete message
		$client = $this->app->http()->client();
		try {
			$response = $client->delete($webhookUrl . '/messages/' . $discordMessageId, [
				'http_errors' => false,
			]);

			$statusCode = $response->getStatusCode();

			if ($statusCode >= 200 && $statusCode < 300) {
				return true;
			}

			// 404 means message doesn't exist (maybe already deleted), treat as success
			if ($statusCode === 404) {
				return true;
			}

			$body = (string)$response->getBody();
			$data = $body ? json_decode($body, true) : null;

			$errorDetails = '';
			if (isset($data['message'])) {
				$errorDetails .= "Message: {$data['message']}\n";
			}
			if (isset($data['code'])) {
				$errorDetails .= "Code: {$data['code']}\n";
			}

			XF::logError("Discord webhook delete message failed: HTTP {$statusCode}\n{$errorDetails}Message ID: {$discordMessageId}\nFull Response: " . print_r($data, true));
			return false;
		} catch (Exception $e) {
			XF::logException($e);
			return false;
		}
	}

	protected function emojiTransformer(): DiscordEmojiTransformer
	{
		/** @var DiscordEmojiTransformer $svc */
		$svc = $this->service('Shoutbox:DiscordEmoji\\Transformer');
		return $svc;
	}
}
