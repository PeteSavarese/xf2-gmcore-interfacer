<?php

namespace PeterSav\GModInterface\Pub\Controller;

use PeterSav\GModInterface\Repository\DiscordLink as DiscordLinkRepository;
use PeterSav\GModInterface\Service\DiscordAPI;
use PeterSav\GModInterface\Service\DiscordOAuth;
use PeterSav\GModInterface\Service\DiscordSync;
use XF;
use XF\Pub\Controller\AbstractController;

class Discord extends AbstractController {
  protected XF\Options $options;
	protected DiscordAPI $discord;
	protected DiscordOAuth $discordOAuth;
	protected DiscordSync $discordSync;
	protected DiscordLinkRepository $discordLinkRepo;

	public function __construct(\XF\App $app, \XF\Http\Request $request) {
		parent::__construct($app, $request);

		$this->options = XF::app()->options();
		$this->discord = XF::app()->service(DiscordAPI::class);
		$this->discordOAuth = XF::app()->service(DiscordOAuth::class);
		$this->discordSync = XF::app()->service(DiscordSync::class);
		$this->discordLinkRepo = XF::repository('PeterSav\GModInterface:DiscordLink');
	}

	private const FLASH_KEY = 'discord_notice';

	private function ErrorView($msg) {
		return $this->view("", "gmod_discord_link_error", ["errorMsg" => $msg]);
	}

	private function setFlashNotice(string $type, string $text): void {
		// $type: success | error | info
		XF::app()->session()->set(self::FLASH_KEY, ['type' => $type, 'text' => $text]);
	}

	private function consumeFlashNotice(): ?array {
		$sess = XF::app()->session();
		$notice = $sess->get(self::FLASH_KEY);
		if ($notice) {
			$sess->remove(self::FLASH_KEY);
		}
		return $notice ?: null;
	}

	public function actionIndex() {
		$discord = null;
		$visitor = XF::visitor();

		if (empty($visitor->user_id)) {
			return $this->view("", "gmod_discord_link_landing", [
        'client_id' => $this->options->gmod_interfacer_discord_bot_client_id,
      ]);
		}

		$activeLink = $this->discordLinkRepo->findActiveByForumId($visitor->user_id);

		if ($activeLink) {
			$discord = $this->discord->getGuildMember($activeLink->discord_id);

			if (empty($discord)) {
				// fallback to their basic discord account info if not in the guild
				$user = $this->discord->getUser($activeLink->discord_id);
				$discord = [
					'user' => $user
				];
			}
		}

		$viewParams = [
      'client_id' => $this->options->gmod_interfacer_discord_bot_client_id,
			"discordAccount" => $discord,
			"guildRoles" => $discord ? $this->discord->getGuildRolesMap() : null,
			"discordId" => $activeLink ? $activeLink->discord_id : 0,
		];

		if ($notice = $this->consumeFlashNotice()) {
			$viewParams['notice'] = $notice;
		}

		return $this->view("", "gmod_discord_link_landing", $viewParams);
	}

	public function actionProcess() {
		if (isset($_GET["error"]) || !isset($_GET["code"])) {
			return $this->ErrorView(isset($_GET["error_description"])
				? $_GET["error_description"]
				: "The returned guild code is invalid or an unexpected error occurred.");
		}

		$visitor = XF::visitor();
		$token = $this->discordOAuth->exchangeCodeForToken($_GET["code"]);

		if (!$token || isset($token['error'])) {
			$msg = $token['error_description'] ?? $token['error'] ?? "Failed to exchange code for token.";
			return $this->ErrorView($msg);
		}

		if (empty($token['access_token'])) {
			return $this->ErrorView("Missing access token in the response.");
		}

		$discordAccount = $this->discordOAuth->getDiscordMemberFromAccessToken($token['access_token']);
		if (!$discordAccount || empty($discordAccount['id'])) {
			return $this->ErrorView("Could not fetch your Discord profile.");
		}

		// Check for existing active links
		$existingForumLink = $this->discordLinkRepo->findActiveByForumId($visitor->user_id);
		if ($existingForumLink) {
			return $this->ErrorView("You already have an active Discord link. Please unlink it first.");
		}

		$existingDiscordLink = $this->discordLinkRepo->findActiveByDiscordId($discordAccount['id']);
		if ($existingDiscordLink) {
			return $this->ErrorView("That Discord account is already linked to a different forum account.");
		}

		// Create the new link
		try {
			$newLink = $this->discordLinkRepo->createLink($visitor->user_id, $discordAccount['id']);
			if (!$newLink->save()) {
				$errors = $newLink->getErrors();
				$errorMsg = $errors ? implode(', ', $errors) : "Failed to create Discord link.";
				return $this->ErrorView($errorMsg);
			}
		} catch (\Exception $e) {
			XF::logError("Discord link creation failed: " . $e->getMessage());
			return $this->ErrorView("An unexpected error occurred while creating your link. Please try again later.");
		}

		try {
			$this->discordOAuth->addUserToGuild($discordAccount['id'], $token['access_token']);
		} catch (\Exception $e) {
			XF::logError("Adding user to Discord guild failed: " . $e->getMessage());
			// Not a critical failure, so we proceed without returning an error.
		}

		$this->discordSync->sync($visitor);
		$this->setFlashNotice('success', 'Your Discord account has been linked successfully.');
		return $this->redirect($this->buildLink('discord'));
	}

	public function actionUnlink() {
		$visitor = XF::visitor();

		if (!$visitor->user_id) {
			return $this->redirect($this->buildLink('discord'));
		}

		$activeLink = $this->discordLinkRepo->findActiveByForumId($visitor->user_id);
		if (!$activeLink) {
			return $this->ErrorView("No active Discord link found for your account.");
		}

		try {
			if (!$activeLink->unlink($visitor->user_id)) {
				return $this->ErrorView("Failed to unlink your Discord account. Please try again.");
			}
		} catch (\Exception $e) {
			XF::logError("Discord unlink failed: " . $e->getMessage());
			return $this->ErrorView("An unexpected error occurred while unlinking your account.");
		}

		$this->discordSync->removeAllManagedRoles($activeLink->discord_id);
		$this->setFlashNotice('success', 'Your Discord account has been unlinked.');
		return $this->redirect($this->buildLink('discord'));
	}

	public static function getActivityDetails(array $activities) {
		return [[
			"description" => " ",
			"title" => "Linking their discord account",
			"url" => "/discord"
		]];
	}
}
