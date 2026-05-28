<?php

namespace BlackTea\SteamAuth\XF\Template;

use XF\Entity\User;

class Templater extends XFCP_Templater
{

    public function addFunctions(array $functions)
    {
        $functions['steam_user_banner'] = 'fnSteamUserBanner';
        $functions['steam_user_banner_by_steam_id'] = 'fnSteamUserBannerBySteamID';
        $functions['can_display_steam_user_banner'] = 'fncanDisplaySteamUserBanner';
        $functions['can_view_steam_profile'] = 'fnCanViewSteamProfile';
        $functions['can_disassociate_steam'] = 'fnCanDisassociateSteam';
        $functions['can_change_steam_profile_privacy'] = 'fnCanChangeSteamProfilePrivacy';
        $functions['can_change_steam_banner_privacy'] = 'fnCanChangeSteamBannerPrivacy';

        return parent::addFunctions($functions);
    }

    public function fnSteamUserBanner($templater, &$escape, User $target = null)
    {
        $visitor = \XF::visitor();
        $escape = false;

        if (!isset($target->Profile->connected_accounts['steam']) || $target->Profile->connected_accounts['steam'] === '') {
            return '';
        }

        if (!$visitor->hasPermission('steamauth', 'viewBanner')) {
            return '';
        }

        if (($target->Privacy->get('allow_view_steam_banner') === 'everyone')
            || ($target->Privacy->get('allow_view_steam_banner') === 'members' && $visitor->user_id > 0)
            || ($visitor->hasPermission('steamauth', 'viewHiddenBanner'))
        ) {
            $steamId = $target->Profile->connected_accounts['steam'];
            return $this->fnSteamUserBannerBySteamID($templater, $escape, $steamId);
        }

        return '';
    }

    public function fnSteamUserBannerBySteamID($templater, &$escape, $steamId)
    {
        $escape = false;
        return $this->renderTemplate('public:blacktea_steamauth_user_banner', array('steamid' => $steamId));
    }

    public function fncanDisplaySteamUserBanner($templater, &$escape, $area = 'before')
    {
        $areaMap = array(
            'before' => '1',
            'after' => '2',
        );

        $options = \XF::options();

        if ($options['blacktea_steamauth_user_banner'] == $areaMap[$area]) {
            return true;
        }

        return false;
    }

    public function fnCanViewSteamProfile($templater, &$escape, User $target = null)
    {
        $visitor = \XF::visitor();
        $options = \XF::options();

        if (in_array($options['blacktea_steamauth_message_macro'], array(false, 0, '0'))) {
            return false;
        }

        if (!isset($target->Profile->connected_accounts['steam']) || $target->Profile->connected_accounts['steam'] === '') {
            return false;
        }

        if (!$visitor->hasPermission('steamauth', 'viewProfile')) {
            return false;
        }

        if (($target->Privacy->get('allow_view_steam') === 'everyone')
            || ($target->Privacy->get('allow_view_steam') === 'members' && $visitor->user_id > 0)
            || ($visitor->hasPermission('steamauth', 'viewHiddenProfile'))
        ) {
            return true;
        }

        return false;
    }

    public function fnCanDisassociateSteam($templater, &$escape, User $target = null)
    {
        if (!$target) {
            $target = \XF::visitor();
        }

        if ($target->user_id <= 0) {
            return false;
        }

        if (!$target->hasPermission('steamauth', 'disassociate')) {
            return false;
        }

        return true;
    }

    public function fnCanChangeSteamProfilePrivacy($templater, &$escape, User $target = null)
    {
        if (!$target) {
            $target = \XF::visitor();
        }

        if ($target->user_id <= 0) {
            return false;
        }

        if (!$target->hasPermission('steamauth', 'changePrivacy')) {
            return false;
        }

        return true;
    }

    public function fnCanChangeSteamBannerPrivacy($templater, &$escape, User $target = null)
    {
        if (!$target) {
            $target = \XF::visitor();
        }

        if ($target->user_id <= 0) {
            return false;
        }

        if (!$target->hasPermission('steamauth', 'changePrivacy')) {
            return false;
        }

        return true;
    }

}