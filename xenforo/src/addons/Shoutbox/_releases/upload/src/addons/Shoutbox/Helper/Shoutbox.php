<?php

namespace Shoutbox\Helper;

use Shoutbox\Repository\Message;
use XF;
use XF\App;

/**
 * Legacy compatibility shim.
 *
 * The shoutbox no longer writes a rendered HTML log to a .txt file.
 * This helper now renders the latest messages via the XenForo templater.
 */
class Shoutbox
{
	protected App $app;

	public function __construct()
	{
		$this->app = XF::app();
	}

	public function regenerateShoutboxHTML(): string
	{
		/** @var Message $repo */
		$repo = XF::repository('Shoutbox:Message');
		$messages = $repo->findLatest(50)->fetch()->reverse(true);

		return $this->app->templater()->renderTemplate('public:shoutbox_messages', [
			'messages' => $messages
		]);
	}
}
