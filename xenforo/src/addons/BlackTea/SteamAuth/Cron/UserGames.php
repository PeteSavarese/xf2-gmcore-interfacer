<?php

namespace BlackTea\SteamAuth\Cron;

class UserGames
{

    public static function run()
    {
        $app = \XF::app();
        $em = $app->em();

        $settings = $app->options();

        $provider = $em->find('XF:ConnectedAccountProvider', 'steam');
        $options = $provider->getValue('options');

        if (!empty($options) && isset($options['client_secret'])) {
            $apiKey = $options['client_secret'];
            $steamApi = new \BlackTea\SteamAuth\Helper\Steam($apiKey);

            $oldestLastUpdated = time() - (int)$settings->blacktea_steamauth_game_update_frequency;
            $limit = $settings->blacktea_steamauth_user_update_limit;

            $db = \XF::db();
            $connectedAccounts = $db->fetchAll('
                SELECT xuca.*, xsgu.game_id FROM xf_user_connected_account AS xuca
                LEFT JOIN xf_steamauth_game_user AS xsgu
                ON xuca.user_id = xsgu.user_id
                WHERE xuca.provider = "steam"
                AND (xsgu.last_updated IS NULL OR xsgu.last_updated < ?)
                GROUP BY xuca.user_id
                HAVING game_id IS NULL OR game_id != 0
                LIMIT ?
            ', array($oldestLastUpdated, $limit));

            foreach ($connectedAccounts as $connectedAccount) {
                try {
                    $userId = $connectedAccount['user_id'];
                    $steamId64 = $connectedAccount['provider_key'];
                    $ownedGames = $steamApi->getOwnedGames($steamId64, true, true);
                    $updateTime = time();

                    if (empty($ownedGames)) {
                        // Prevent user from being checked repeatedly
                        $db->query('
                            INSERT IGNORE INTO xf_steamauth_game_user
                            (game_id, user_id, playtime, playtime_recent, last_updated)
                            VALUES (?, ?, ?, ?, ?)
                        ', array(0, $userId, 0, 0, $updateTime));
                    } else {
                        foreach ($ownedGames as $ownedGame) {
                            $data = array(
                                $ownedGame['appid'],
                                $ownedGame['name'],
                                'https://steamcdn-a.akamaihd.net/steam/apps/' . $ownedGame['appid'] . '/capsule_184x69.jpg',
                                'https://steamcommunity.com/app/' . $ownedGame['appid']
                            );

                            $db->query('
                            INSERT IGNORE INTO xf_steamauth_game
                            (game_id, label, image, url)
                            VALUES (?, ?, ?, ?)
                        ', $data);

                            $data = array(
                                $ownedGame['appid'],
                                $userId,
                                isset($ownedGame['playtime_forever']) ? $ownedGame['playtime_forever'] : 0,
                                isset($ownedGame['playtime_2weeks']) ? $ownedGame['playtime_2weeks'] : 0,
                                $updateTime,
                                isset($ownedGame['playtime_forever']) ? $ownedGame['playtime_forever'] : 0,
                                isset($ownedGame['playtime_2weeks']) ? $ownedGame['playtime_2weeks'] : 0,
                                $updateTime
                            );

                            $db->query('
                            INSERT IGNORE INTO xf_steamauth_game_user
                            (game_id, user_id, playtime, playtime_recent, last_updated)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE playtime = ?, playtime_recent = ?, last_updated = ?
                        ', $data);
                        }
                    }
                } catch (\Exception $e) {
                    if ($app->options()->blacktea_steamauth_verbose_log) {
                        \XF::logException($e, false);
                    }
                }

                sleep($settings->blacktea_steamauth_api_sleep);
            }
        }
    }

}