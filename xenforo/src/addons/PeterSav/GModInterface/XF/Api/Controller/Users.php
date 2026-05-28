<?php

namespace PeterSav\GModInterface\XF\Api\Controller;

use PeterSav\GModInterface\Helper\CoreDatabase;
use XF\Api\Controller\UsersController;

class Users extends UsersController {
	public function actionGetFindName() {
		$response = parent::actionGetFindName();
		$apiResult = $response->getApiResult();

		if ($response instanceof \XF\Api\Mvc\Reply\ApiResult && $apiResult instanceof \XF\Api\Result\ArrayResult) {
			$data = $apiResult->getResult();

			if (isset($data['exact'])) {
				$this->augmentUserData($data['exact']);
			}

			if (isset($data['recommendations']) && !empty($data['recommendations']) && $data['recommendations'] instanceof \XF\Api\Result\EntityResults) {
				foreach ($data['recommendations']->getEntityResults() as $user) {
					$this->augmentUserData($user);
				}
			}

			$apiResult->setResult($data);
		}

		return $response;
	}

	protected function augmentUserData(\XF\Api\Result\EntityResult $user) {
		$userId = $user->getEntity()->user_id;
		$user->includeExtra('is_banned', (bool)$user->getEntity()->is_banned);
		$user->includeExtra('steam_id', $this->getUserSteamId($userId));
		$this->includeDiscordInformation($user);
	}

	protected function getUserSteamId(int $userId): ?string {
		return \XF::finder('XF:UserConnectedAccount')
			->where('user_id', $userId)
			->where('provider', 'steam')
			->fetchOne()?->provider_key;
	}

	protected function includeDiscordInformation(\XF\Api\Result\EntityResult $user): void {
		$em = CoreDatabase::getCoreEntityManager();
		if (!$em) {
			return;
		}

		$result = $em
			->getFinder('PeterSav\GModInterface:DiscordLink')
			->where('forum_id', $user->getEntity()->user_id)
			->where('unlinked_at', null)
			->fetchOne();

		$user->includeExtra('discord_id', $result?->discord_id);
		$user->includeExtra('discord_link_created_at', $result?->created_at);
	}
}