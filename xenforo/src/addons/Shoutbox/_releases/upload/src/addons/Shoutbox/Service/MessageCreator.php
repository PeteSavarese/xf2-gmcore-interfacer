<?php

namespace Shoutbox\Service;

use LogicException;
use Shoutbox\Entity\Message;
use Shoutbox\Repository\Message as MessageRepo;
use XF;
use XF\Entity\User;

/**
 * Service for creating new shoutbox messages from XenForo users.
 *
 * Handles:
 * - Message validation and sanitization
 * - Anti-spam checks (duplicate detection, rate limiting)
 * - Discord ping blocking
 * - Client-side deduplication via nonce tracking
 * - Cache updates for real-time polling
 */
class MessageCreator
{
	/**
	 * Check if a message contains Discord mentions or pings.
	 *
	 * Blocks @everyone, @here, user mentions, role mentions, channel mentions,
	 * and custom emoji pings to prevent abuse when messages are forwarded to Discord.
	 *
	 * @param string $message The message text to check
	 * @return bool True if Discord pings/mentions are detected, false otherwise
	 */
	protected function containsDiscordPings(string $message): bool
	{
		$patterns = [
			'/@everyone/',
			'/@here/',
			'/<@!??\d+>/',
			'/<@&\d+>/',
			'/<#\d+>/',
			'/<!@everyone>/',
			'/<!@here>/',
			'/<!@\d+>/',
			'/<!@&\d+>/',
			'/<!#\d+>/',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $message)) {
				return true;
			}
		}

		$lower = strtolower($message);
		return (strpos($lower, '@everyone') !== false || strpos($lower, '@here') !== false);
	}

	/**
	 * @var User|null The user creating the message (defaults to current visitor)
	 */
	protected ?User $user = null;

	/**
	 * Set a specific user as the message creator.
	 *
	 * If not called, defaults to the current visitor (XF::visitor()).
	 *
	 * @param User $user The user who will be the message author
	 * @return void
	 */
	public function setUser(User $user): void
	{
		$this->user = $user;
	}

	/**
	 * Get the user creating this message.
	 *
	 * @return User The message author (either explicitly set or current visitor)
	 */
	public function getUser(): User
	{
		return $this->user ?: XF::visitor();
	}

	/**
	 * Create a new shoutbox message.
	 *
	 * Validation performed:
	 * - User must be logged in
	 * - Client nonce must be provided (for deduplication)
	 * - User must not be banned from shoutbox
	 * - Message must not be empty
	 * - Message must not contain Discord pings
	 *
	 * Anti-spam features:
	 * - Checks session for duplicate nonce (prevents double-submit)
	 * - Checks for identical message posted within 10 seconds
	 *
	 * @param string $rawMessage The message text from user input
	 * @param string|null $clientNonce Unique client-generated token for deduplication
	 * @return Message The created (or previously created) message entity
	 * @throws LogicException If validation fails or user lacks permission
	 */
	public function create(string $rawMessage, ?string $clientNonce = null): Message
	{
		$user = $this->getUser();

		if (!$user || !$user->user_id) {
			throw new LogicException('You must be logged in to post messages to the shoutbox.');
		}

		$clientNonce = $clientNonce !== null ? trim($clientNonce) : '';
		if ($clientNonce === '') {
			throw new LogicException('Unable to submit message (missing request token). Please try again.');
		}
		$clientNonce = substr($clientNonce, 0, 64);

		if (method_exists($user, 'isBannedFromShoutbox')
			&& call_user_func([$user, 'isBannedFromShoutbox'])) {
			throw new LogicException('You are banned from posting in the shoutbox.');
		}

		$message = trim($rawMessage);
		if ($message === '') {
			throw new LogicException('Please enter a valid message.');
		}

		if ($this->containsDiscordPings($message)) {
			throw new LogicException('Discord mentions and pings are not allowed in the shoutbox.');
		}

		// Anti-spam / de-dupe: if the browser retries or the user double-clicks after a timeout,
		// it will send the same client nonce again. Return the previously created message.
		$session = XF::session();
		$lastSend = $session ? $session->shoutboxLastSend : null;
		if (is_array($lastSend)
			&& ($lastSend['nonce'] ?? null) === $clientNonce
			&& !empty($lastSend['message_id'])
		) {
			$existing = XF::em()->find('Shoutbox:Message', (int)$lastSend['message_id']);
			if ($existing) {
				return $existing;
			}
		}

		// Anti-spam: prevent posting the exact same message repeatedly in a short window.
		/** @var Message|null $lastMessage */
		$lastMessage = XF::finder('Shoutbox:Message')
			->where('user_id', $user->user_id)
			->order('id', 'DESC')
			->fetchOne();
		if ($lastMessage
			&& $lastMessage->message === $message
			&& strtotime($lastMessage->created_on) >= (time() - 10)
		) {
			if ($session) {
				$session->shoutboxLastSend = [
					'nonce' => $clientNonce,
					'message_id' => (int)$lastMessage->id,
					'time' => time()
				];
			}
			return $lastMessage;
		}

		/** @var Message $entity */
		$entity = XF::em()->create('Shoutbox:Message');
		$entity->user_id = $user->user_id;
		$entity->message = $message;
		$entity->save();

		if ($session) {
			$session->shoutboxLastSend = [
				'nonce' => $clientNonce,
				'message_id' => (int)$entity->id,
				'time' => time()
			];
		}

		/** @var MessageRepo $repo */
		$repo = XF::repository('Shoutbox:Message');
		$repo->setLastIdCache((int)$entity->id);

		// Send to Discord (bot integration)
		$discordBot = new DiscordBotMessenger(XF::app());
		$discordId = $discordBot->sendMessage($user, $message);
		if ($discordId) {
			$entity->discord_message_id = $discordId;
			$entity->save();
		}

		return $entity;
	}
}
