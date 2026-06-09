<?php

namespace Shoutbox\Admin\Controller;

use XF;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Bans extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('ban');
	}

	public function actionIndex(ParameterBag $params)
	{
		return $this->bansList('active', $params);
	}

	public function actionExpired(ParameterBag $params)
	{
		return $this->bansList('expired', $params);
	}

	protected function bansList(string $type, ParameterBag $params)
	{
		$now = date('Y-m-d H:i:s');

		$finder = $this->finder('Shoutbox:ShoutboxBan')
			->with(['User', 'BannedBy'])
			->order('banned_on', 'DESC');

		if ($type === 'expired') {
			$finder->where('expires_on', '<=', $now);
			$finder->where('expires_on', '!=', null);
		} else {
			$finder->whereOr([
				['expires_on', '=', null],
				['expires_on', '>', $now]
			]);
		}

		$perPage = 50;
		$page = $this->filterPage($params->page);
		$total = $finder->total();
		$finder->limitByPage($page, $perPage);
		$bans = $finder->fetch();

		$viewParams = [
			'tab' => ($type === 'expired' ? 'expired' : 'active'),
			'bans' => $bans,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
			'now' => $now,
		];

		return $this->view('Shoutbox:Bans\\List', 'shoutbox_bans_list', $viewParams);
	}

	public function actionHistory(ParameterBag $params)
	{
		$historyFinder = $this->finder('Shoutbox:ShoutboxBanHistory')
			->with(['User', 'BannedBy', 'UnbannedBy'])
			->order('unbanned_on', 'DESC');

		$user = null;
		if ($params->user_id) {
			$user = $this->em()->find('XF:User', $params->user_id);
			$historyFinder->where('user_id', $params->user_id);
		}

		$perPage = 50;
		$page = $this->filterPage($params->page);
		$total = $historyFinder->total();
		$historyFinder->limitByPage($page, $perPage);
		$history = $historyFinder->fetch();

		$viewParams = [
			'tab' => 'history',
			'user' => $user,
			'history' => $history,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
		];

		return $this->view('Shoutbox:Bans\\History', 'shoutbox_ban_history', $viewParams);
	}

	public function actionDelete(ParameterBag $params)
	{
		$ban = $this->assertBanExists($params->user_id);

		if ($this->isPost()) {
			$ban->delete();
			return $this->redirect($this->buildLink('shoutbox-bans'));
		}

		$viewParams = [
			'ban' => $ban,
			'user' => $ban->User,
		];

		return $this->view('Shoutbox:Bans\\Delete', 'shoutbox_ban_delete_confirm', $viewParams);
	}

	protected function assertBanExists($userId)
	{
		$userId = (int)($userId ?? 0);
		if (!$userId) {
			throw $this->exception($this->notFound(XF::phrase('requested_page_not_found')));
		}

		$ban = $this->em()->find('Shoutbox:ShoutboxBan', $userId, ['User', 'BannedBy']);
		if (!$ban) {
			throw $this->exception($this->notFound(XF::phrase('requested_page_not_found')));
		}

		return $ban;
	}
}
