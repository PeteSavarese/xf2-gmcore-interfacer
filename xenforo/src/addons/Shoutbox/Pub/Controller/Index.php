<?php

namespace Shoutbox\Pub\Controller;

use LogicException;
use Shoutbox\Entity\Message;
use Shoutbox\Entity\ShoutboxBan;
use Shoutbox\Repository\Message as MessageRepo;
use Shoutbox\Service\DiscordBotMessenger;
use Shoutbox\Service\MessageCreator;
use XF;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

/**
 * Public controller for shoutbox actions.
 */
class Index extends AbstractController
{
	private const MAX_POLL_WAIT_SECONDS = 5;

	protected function getMessageRepo(): MessageRepo
	{
		/** @var MessageRepo $repo */
		$repo = $this->repository('Shoutbox:Message');
		return $repo;
	}

	/**
	 * Retrieve message by ID
	 *
	 * @param int $messageId Message ID to fetch
	 * @return Message|null Message entity, or null if not found
	 */
	protected function getMessageOrError(int $messageId): ?Message
	{
		/** @var Message|null $message */
		$message = $this->em()->find('Shoutbox:Message', $messageId);
		if (!$message) {
			return null;
		}

		return $message;
	}

	/**
	 * Check if the current visitor can edit/delete a message.
	 *
	 * @param Message $message Message to check permissions for
	 * @return bool True if user can edit/delete the message
	 */
	protected function canEditMessage(Message $message): bool
	{
		$visitor = XF::visitor();
		if ($visitor->hasPermission('shoutbox', 'can_delete_messages') || $visitor->user_id === $message->user_id) {
			return true;
		}

		return ($visitor->user_id && (int)$visitor->user_id === (int)$message->user_id);
	}

	/**
	 * Check if a message contains Discord mentions or pings.
	 *
	 * @param string $message Message text to check
	 * @return bool True if Discord pings are found
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

	protected function assertCanBan()
	{
		if (!XF::visitor()->hasPermission('shoutbox', 'can_ban_user'))
		{
			throw $this->exception($this->noPermission());
		}
	}

	protected function getActiveBanFinder()
	{
		$now = date('Y-m-d H:i:s');
		return $this->finder('Shoutbox:ShoutboxBan')
			->with(['User', 'BannedBy'])
			->whereOr([
				['expires_on', '=', null],
				['expires_on', '>', $now]
			])
			->order('banned_on', 'DESC');
	}

	/**
	 * Display the full shoutbox widget with recent messages.
	 * Loads the 50 most recent messages and returns shoutbox template.
	 *
	 * @return View View containing the shoutbox with messages
	 */
	public function actionIndex()
	{
		$messages = $this->getMessageRepo()->findLatest(50)->fetch();
		$messages = $messages->reverse(true);
		$lastId = $messages->count() ? (int)$messages->last()->id : 0;

		return $this->view('', 'shoutbox_full_box', [
			'messages' => $messages,
			'lastId' => $lastId
		]);
	}

	public function actionBans()
	{
		$this->assertCanBan();

		$bans = $this->getActiveBanFinder()->fetch();

		return $this->view('', 'shoutbox_bans', [
			'bans' => $bans
		]);
	}

	public function actionUnban()
	{
		$this->assertCanBan();

		$userId = $this->filter('user_id', 'uint');
		if (!$userId)
		{
			return $this->error('Missing user_id');
		}

		/** @var ShoutboxBan|null $ban */
		$ban = $this->em()->find('Shoutbox:ShoutboxBan', $userId);
		if (!$ban)
		{
			return $this->error(XF::phrase('requested_ban_not_found'));
		}

		if ($this->isPost())
		{
			$ban->delete();

			$reply = $this->view();
			$reply->setJsonParams([
				'status' => 'ok',
				'message' => 'User has been unbanned from shoutbox.',
				'shoutboxRefresh' => true,
			]);
			return $reply;
		}

		return $this->view('', 'shoutbox_unban_confirm', [
			'ban' => $ban
		]);
	}

	/**
	 * Handle long-polling requests for real-time shoutbox updates.
	 *
	 * @return View JSON response with messages, mode, last_id, and generation
	 */
	public function actionPoll(): View
	{
		$lastId = $this->filter('last_id', 'uint');
		$wait = $this->filter('wait', 'bool');
		$load = $this->filter('load', 'bool');
		$clientGeneration = $this->filter('generation', 'uint');

		$repo = $this->getMessageRepo();
		$serverGeneration = $repo->getGenerationFromCache();

		if ($wait && $lastId) {
			$timeoutAt = time() + self::MAX_POLL_WAIT_SECONDS;
			while (time() < $timeoutAt) {
				if ($repo->getLastIdFromCache() > $lastId) {
					break;
				}

				usleep(self::MAX_POLL_WAIT_SECONDS * 1000);
			}
		}

		// Force full reload if generation changed for deletions/edits
		$generationChanged = $clientGeneration && ($serverGeneration !== $clientGeneration);

		if ($load || !$lastId || $generationChanged) {
			$messages = $repo->findLatest(50)->fetch()->reverse(true);
			$mode = 'replace';
		} else {
			$messages = $repo->findAfterId($lastId, 50)->fetch();
			$mode = 'append';
		}

		$newLastId = $lastId;
		if ($messages->count()) {
			$newLastId = (int)$messages->last()->id;
		}

		$html = $this->app()->templater()->renderTemplate('public:shoutbox_messages', [
			'messages' => $messages,
			// TODO: Find a better way to pass the visitor to the template using existing Templaer method
			// I hate myself so much for this, but renderTemplate doesn't have XF::visitor unlesss
			// supplied like this.
			'xf' => [
				'visitor' => XF::visitor()
			]
		]);

		$reply = $this->view();
		$reply->setJsonParams([
			'mode' => $mode,
			'messages_html' => $html, // Note: `html` is reserved by XF's JSON renderer for the rendered view fallback
			'last_id' => $newLastId,
			'generation' => $serverGeneration,
			'server_time' => time()
		]);

		return $reply;
	}

	/**
	 * Load older shoutbox messages (history) before a given message id.
	 */
	public function actionOlder()
	{
		$beforeId = $this->filter('before_id', 'uint');
		$limit = $this->filter('limit', 'uint');
		$limit = $limit ?: 50;
		$limit = max(1, min(200, $limit));

		if (!$beforeId)
		{
			return $this->error('Missing before_id');
		}

		$repo = $this->getMessageRepo();
		$messages = $repo->findBeforeId($beforeId, $limit)->fetch()->reverse(true);

		$hasMore = false;
		$firstId = 0;
		if ($messages->count())
		{
			$firstId = (int)$messages->first()->id;
			$hasMore = (bool)XF::db()->fetchOne('SELECT 1 FROM shoutbox_message WHERE id < ? LIMIT 1', $firstId);
		}

		$html = $this->app()->templater()->renderTemplate('public:shoutbox_messages', [
			'messages' => $messages,
			'xf' => [
        // Please refer to actionPoll comment for my feelings about this
				'visitor' => XF::visitor()
			]
		]);

		$reply = $this->view();
		$reply->setJsonParams([
			'messages_html' => $html,
			'first_id' => $firstId,
			'has_more' => $hasMore
		]);

		return $reply;
	}

	/**
	 * Create a new shoutbox message from user input.
   *
	 * @param ParameterBag $params Route parameters (unused)
	 * @return View|Error JSON success response or error message
	 */
	public function actionSendmessage(ParameterBag $params)
	{
		$this->assertPostOnly();
		$rawMessage = $this->filter('message', 'str');
		$clientNonce = $this->filter('client_nonce', 'str');

		try {
			$creator = new MessageCreator();
			$message = $creator->create($rawMessage, $clientNonce);

			$this->getMessageRepo()->bumpGeneration();
		} catch (LogicException $e) {
			return $this->error($e->getMessage());
		}

		$reply = $this->view();
		$reply->setJsonParams([
			'status' => 'ok',
			'message_id' => (int)$message->id,
			'shoutboxRefresh' => true,
		]);

		return $reply;
	}
	/**
	 * Edit an existing shoutbox message.
	 *
	 * GET: Display edit form
	 * POST: Save edited message and trigger refresh
	 *
	 * @param ParameterBag $params Route parameters containing message_id
	 * @return View|Error View or error response
	 */
	public function actionEdit(ParameterBag $params)
	{
		$messageId = (int)$params->message_id;
		$message = $this->getMessageOrError($messageId);
		if (!$message) {
			return $this->error('Message not found.');
		}

		if (!$this->canEditMessage($message)) {
			return $this->noPermission();
		}

		if ($this->isPost()) {
			$newMessage = $this->filter('message', 'str');
			$newMessage = trim($newMessage);
			if ($newMessage === '') {
				return $this->error('Please enter a valid message.');
			}

			if ($this->containsDiscordPings($newMessage)) {
				return $this->error('Discord mentions and pings are not allowed in the shoutbox.');
			}

			$message->message = $newMessage;
			$message->save();

			// Update message in Discord if it was synced
			if ($message->discord_message_id) {
				$discordBot = new DiscordBotMessenger($this->app);
				$discordBot->editMessage($message->discord_message_id, $newMessage);
			}

			$this->getMessageRepo()->bumpGeneration();

			$reply = $this->view();
			$reply->setJsonParams([
				'status' => 'ok',
				'message' => XF::phrase('your_changes_have_been_saved'),
				'shoutboxRefresh' => true,
			]);
			return $reply;
		}

		return $this->view('', 'shoutbox_edit_message', [
			'message' => $message
		]);
	}

	/**
	 * Ban a user from the shoutbox.
	 *
	 * GET: Display ban confirmation overlay (native XF confirmation)
	 * POST: Create ban entity and redirect with message
	 *
	 * @param ParameterBag $params Route parameters containing message_id
	 * @return View|Error|Redirect View, error, or redirect response
	 */
	public function actionBan(ParameterBag $params)
	{
		$messageId = (int)$params->message_id;
		$message = $this->getMessageOrError($messageId);
		if (!$message) {
			return $this->error('Message not found.');
		}

		if (!$message->User) {
			return $this->error('Cannot ban: message has no associated user.');
		}

		if (!XF::visitor()->hasPermission('shoutbox', 'can_ban_user')) {
			return $this->noPermission();
		}

		$user = $message->User;
		$existingBan = $this->em()->find('Shoutbox:ShoutboxBan', $user->user_id);

		if ($existingBan) {
			return $this->error(XF::phrase('this_user_is_already_banned_from_shoutbox'));
		}

		if ($this->isPost()) {
			/** @var ShoutboxBan $ban */
			$ban = $this->em()->create('Shoutbox:ShoutboxBan');
			$ban->user_id = $user->user_id;
			$ban->ban_user_id = XF::visitor()->user_id;
			$ban->reason = $this->filter('reason', 'str');

			$banLengthRaw = trim((string)$this->filter('ban_length', 'str'));
			if ($banLengthRaw !== '' && ctype_digit($banLengthRaw))
			{
				$banLengthSeconds = min((int)$banLengthRaw, 315360000); // cap at 10y lol
				if ($banLengthSeconds > 0)
				{
					$expiresTs = time() + $banLengthSeconds;
					$ban->expires_on = date('Y-m-d H:i:s', $expiresTs);
				}
			}
			else if ($banLengthRaw === 'temporary')
			{
				$banLengthValue = $this->filter('ban_length_value', 'uint');
				$banLengthUnit = $this->filter('ban_length_unit', 'str');
				$allowedUnits = ['minutes', 'hours', 'days', 'weeks', 'months', 'years'];
				if (!$banLengthValue || !in_array($banLengthUnit, $allowedUnits, true))
				{
					return $this->error(XF::phrase('please_enter_a_valid_date'));
				}

				$expiresTs = strtotime('+' . $banLengthValue . ' ' . $banLengthUnit);
				if (!$expiresTs || $expiresTs <= time())
				{
					return $this->error(XF::phrase('please_enter_a_valid_date'));
				}

				$ban->expires_on = date('Y-m-d H:i:s', $expiresTs);
			}
			else
			{
				$expiresRaw = trim((string)$this->filter('expires_on', 'str'));
				if ($expiresRaw !== '')
				{
					$expiresRaw = str_replace('T', ' ', $expiresRaw);
					$expiresTs = strtotime($expiresRaw);
					if (!$expiresTs || $expiresTs <= time())
					{
						return $this->error(XF::phrase('please_enter_a_valid_date'));
					}

					$ban->expires_on = date('Y-m-d H:i:s', $expiresTs);
				}
			}
			$ban->save();

			$reply = $this->view();
			$reply->setJsonParams([
				'status' => 'ok',
				'message' => XF::phrase('user_has_been_banned_from_shoutbox'),
			]);

			return $reply;
		}

		$viewParams = [
			'message' => $message,
			'user' => $user,
		];
		return $this->view('', 'shoutbox_ban_confirm', $viewParams);
	}

	public function actionDelete(ParameterBag $params)
	{
		$messageId = $params->message_id;

		$message = $this->getMessageOrError($messageId);
		if (!$message) {
			return $this->error('Message not found.');
		}

		if (!$this->canEditMessage($message)) {
			return $this->noPermission();
		}

		if ($this->isPost()) {
			$message->delete();

			$reply = $this->view();
			$reply->setJsonParams([
				'status' => 'ok',
				'message' => XF::phrase('your_changes_have_been_saved'),
				'shoutboxRefresh' => true,
			]);

			return $reply;
		}

		$contentTitle = XF::app()->stringFormatter()->wholeWordTrim((string)$message->message, 100);

		return $this->view('', 'shoutbox_delete_confirm', [
			'message' => $message,
			'contentTitle' => $contentTitle !== '' ? $contentTitle : 'Shoutbox message'
		]);
	}
}
