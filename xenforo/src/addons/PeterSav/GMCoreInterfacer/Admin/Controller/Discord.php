<?php

namespace PeterSav\GModInterface\Admin\Controller;

use XF;
use XF\Mvc\ParameterBag;
use XF\Repository\UserGroupRepository;
use PeterSav\GModInterface\Service\DiscordAPI;
use PeterSav\GModInterface\Helper\CoreDatabase;
use PeterSav\GModInterface\Entity\DiscordRoleForumGroup;

class Discord extends \XF\Admin\Controller\AbstractController {
	private XF\Service\AbstractService|DiscordAPI $discord;

	public function __construct(\XF\App $app, \XF\Http\Request $request) {
		parent::__construct($app, $request);
		$this->discord = XF::app()->service(DiscordAPI::class);
	}

	protected function preDispatchController($action, ParameterBag $params) {
		$this->assertAdminPermission('gmod_manage_store_ranks');
	}

	public function actionManage() {
		$mappings = CoreDatabase::getCoreEntityManager()
			->getFinder('PeterSav\GModInterface:DiscordRoleForumGroup')
			->fetch();

		$viewParams = array(
			'discordRoleMappings' => $mappings,
			'guildRoles' => $this->discord->getGuildRolesMap(),
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		);

		return $this->view('', 'gmod_discord_role_mappings', $viewParams);
	}

	public function actionEdit(ParameterBag $params) {
		$em = CoreDatabase::getCoreEntityManager();

		if ($params->mapping_id) {
			// Editing existing mapping
			$mapping = $em->find('PeterSav\GModInterface:DiscordRoleForumGroup', $params->mapping_id);

			if (!$mapping) {
				return $this->error("Invalid mapping ID supplied! Mapping ID does not exist in database!");
			}
		} else {
			// Creating new mapping
			$mapping = $em->create('PeterSav\GModInterface:DiscordRoleForumGroup');
		}

		return $this->view('', 'gmod_discord_mapping_edit', [
			"mapping" => $mapping,
			'guildRoles' => $this->discord->getGuildRolesMap(),
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		]);
	}

	public function actionAdd() {
		$mapping = CoreDatabase::getCoreEntityManager()->create('PeterSav\GModInterface:DiscordRoleForumGroup');

		return $this->view('', 'gmod_discord_mapping_edit', [
			'mapping' => $mapping,
			'guildRoles' => $this->discord->getGuildRolesMap(),
			'userGroups' => $this->repository(UserGroupRepository::class)->getUserGroupTitlePairs(),
		]);
	}

	public function actionSave(ParameterBag $params) {
		$this->assertPostOnly();

		$input = $this->filter([
			'discord_role_id' => 'str',
			'forum_group_id' => 'uint',
		]);

		// Validate required fields
		if (empty($input['discord_role_id'])) {
			return $this->error("Discord role selection is required.");
		}

		if (!$input['forum_group_id']) {
			return $this->error("Forum user group selection is required.");
		}

		$em = CoreDatabase::getCoreEntityManager();

		try {
			if ($params->mapping_id) {
				// Update existing mapping
				$mapping = $em->find('PeterSav\GModInterface:DiscordRoleForumGroup', $params->mapping_id);

				if (!$mapping) {
					return $this->error("Invalid mapping ID supplied! Mapping does not exist in database!");
				}
			} else {
				// Create new mapping
				$mapping = $em->create('PeterSav\GModInterface:DiscordRoleForumGroup');
				$mapping->created_by_forum_id = \XF::visitor()->user_id;
			}

			$mapping->discord_role_id = $input['discord_role_id'];
			$mapping->forum_group_id = $input['forum_group_id'];

			if (!$mapping->save()) {
				return $this->error($mapping->getErrors());
			}

			return $this->redirect($this->buildLink('discord/manage') . $this->buildLinkHash($mapping->mapping_id));

		} catch (\Exception $e) {
			\XF::logException($e);
			return $this->error("An error occurred while saving the role mapping. Please try again.");
		}
	}


	public function actionDelete(ParameterBag $params) {
		if (!$params->mapping_id) {
			return $this->error("Invalid mapping ID supplied!");
		}

		$em = CoreDatabase::getCoreEntityManager();
		$mapping = $em->find('PeterSav\GModInterface:DiscordRoleForumGroup', $params->mapping_id);

		if (!$mapping) {
			return $this->error("Role mapping not found!");
		}

		$guildRoles = $this->discord->getGuildRolesMap();
		$roleData = $guildRoles[$mapping->discord_role_id] ?? null;
		$roleName = $roleData ? $roleData['name'] : "Unknown Role ({$mapping->discord_role_id})";

		/** @var \XF\ControllerPlugin\DeletePlugin $plugin */
		$plugin = $this->plugin('XF:Delete');
		return $plugin->actionDelete(
			$mapping,
			$this->buildLink('discord/delete', $mapping),
			$this->buildLink('discord/edit', $mapping),
			$this->buildLink('discord/manage'),
			"Discord Role: {$roleName}"
		);
	}
}