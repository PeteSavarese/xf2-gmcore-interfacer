<?php

namespace BlackTea\SteamAuth\Cron;

class Cleanup
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

            $db = \XF::db();

            $connectedAccounts = $db->fetchAll('
                SELECT xuca.*, xsgu.game_id FROM xf_user_connected_account AS xuca
                LEFT JOIN xf_steamauth_game_user AS xsgu
                ON xuca.user_id = xsgu.user_id
                WHERE xuca.provider = "steam"
                AND (xsgu.last_updated IS NULL OR xsgu.last_updated < ?)
                GROUP BY xuca.user_id
                HAVING game_id = 0
            ', array($oldestLastUpdated));

            foreach ($connectedAccounts as $connectedAccount) {
                try {
                    $userId = $connectedAccount['user_id'];
                    $steamId64 = $connectedAccount['provider_key'];
                    $ownedGames = $steamApi->getOwnedGames($steamId64, true, true);
                    $updateTime = time();

                    if (empty($ownedGames)) {
                        $db->query('
                            UPDATE xf_steamauth_game_user
                            SET last_updated = ?
                            WHERE game_id = 0 AND user_id = ?
                        ', $data = array($updateTime, $userId));
                    } else {
                        $db->query('
                            DELETE FROM xf_steamauth_game_user
                            WHERE game_id = 0 AND user_id = ?
                        ', array($userId));
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