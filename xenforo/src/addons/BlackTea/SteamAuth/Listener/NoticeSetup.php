<?php

namespace BlackTea\SteamAuth\Listener;

use XF\Pub\App;
use XF\NoticeList;

class NoticeSetup
{

    public static function run(App $app, NoticeList $noticeList, array $pageParams)
    {
        if (isset($pageParams['pageSection']) && $pageParams['pageSection'] == 'steamauth') {
            $visitor = \XF::visitor();

            if ($visitor->getEntityId() !== 0) {
                $profile = $visitor->getRelation('Profile');
                $connectedAccounts = $profile->connected_accounts;

                if (isset($connectedAccounts['steam'])) {
                    $db = \XF::db();
                    $isUnableToFetchGames = (bool)$db->fetchOne('SELECT user_id FROM xf_steamauth_game_user WHERE game_id = 0 AND user_id = ?', array($visitor->getEntityId()));

                    if ($isUnableToFetchGames) {
                        $templater = $app->templater();
                        $html = $templater->renderTemplate('public:blacktea_steamauth_notice_cannot_fetch', $pageParams);
                        $noticeList->addNotice('blacktea_steamauth_cannot_fetch', 'block', $html);
                    }
                }
            }
        }
    }

}