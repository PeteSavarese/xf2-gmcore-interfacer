<?php

namespace Shoutbox\Widget;

use Shoutbox\Repository\Message;
use Shoutbox\Repository\ShoutboxBan;
use XF\Widget\AbstractWidget;

class Shoutbox extends AbstractWidget
{
	public function render()
	{
		/** @var Message $repo */
		$repo = $this->app->repository('Shoutbox:Message');
		/** @var ShoutboxBan $banRepo */
		$banRepo = $this->app->repository('Shoutbox:ShoutboxBan');
		$messages = $repo->findLatest(50)->fetch()->reverse(true);
		$lastId = $messages->count() ? (int)$messages->last()->id : 0;
		$activeBan = $banRepo->getActiveBanForVisitor();

		return $this->renderer('shoutbox_full_box', [
			'messages' => $messages,
			'lastId' => $lastId,
			'activeBan' => $activeBan,
		]);
	}
}
