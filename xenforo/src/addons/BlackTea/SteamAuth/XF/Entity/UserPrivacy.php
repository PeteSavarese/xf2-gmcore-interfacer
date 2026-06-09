<?php

namespace BlackTea\SteamAuth\XF\Entity;

class UserPrivacy extends XFCP_UserPrivacy
{

    public static function getStructure(\XF\Mvc\Entity\Structure $structure)
    {
        $result = parent::getStructure($structure);

        $result->columns['allow_view_steam'] = array(
            'type' => self::STR,
            'default' => 'everyone',
            'allowedValues' => array(
                'everyone',
                'members',
                'none'
            ),
            'verify' => 'verifyPrivacyChoice'
        );

        $result->columns['allow_view_steam_banner'] = array(
            'type' => self::STR,
            'default' => 'everyone',
            'allowedValues' => array(
                'everyone',
                'members',
                'none'
            ),
            'verify' => 'verifyPrivacyChoice'
        );

        return $result;
    }

}