<?php

namespace BlackTea\SteamAuth\Listener;

use XF\Pub\App;

class NavigationSetup
{

    public static function run(App $app, &$navigationFlat, &$navigationTree)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('steamauth', 'viewAnalytics')) {
            $blacklisted = array(
                'steamauth',
                'steamauthActivePlayers',
                'steamauthActiveGames',
                'steamauthPlayedGames',
                'steamauthOwnedGames',
            );

            foreach ($navigationFlat as $key => $item) {
                if (\in_array($key, $blacklisted)) {
                    unset($navigationFlat[$key]);
                }
            }

            foreach ($navigationTree as $key => $item) {
                if (\in_array($key, $blacklisted)) {
                    unset($navigationTree[$key]);
                }
            }
        }
    }

}