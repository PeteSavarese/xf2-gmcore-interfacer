<?php

namespace BlackTea\SteamAuth\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Steam extends AbstractController
{

    /**
     * @param ParameterBag $params
     * @throws \Exception
     */
    public function actionGetsummaries(ParameterBag $params)
    {
        header('Content-Type: application/json');

        $app = $this->app();
        $em = $app->em();

        $profiles = array();
        $steamIds = $params->get('steamids');
        preg_match('/[^0-9-]/', $steamIds, $matches);

        if (empty($matches)) {
            $provider = $em->find('XF:ConnectedAccountProvider', 'steam');
            $options = $provider->getValue('options');

            if (!empty($options) && isset($options['client_secret'])) {
                $apiKey = $options['client_secret'];
                $steamIds = explode('-', $steamIds);

                try {
                    $steamApi = new \BlackTea\SteamAuth\Helper\Steam($apiKey);
                    $profiles = $steamApi->getPlayerSummaries($steamIds);
                } catch (\Exception $e) {
                    if ($app->options()->blacktea_steamauth_verbose_log) {
                        \XF::logException($e, false);
                    }
                }
            }
        }

        echo json_encode(array('profiles' => $profiles));
        die;
    }

    public function actionActivePlayers(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('steamauth', 'viewAnalytics')) {
            return $this->noPermission();
        }

        $enabledPages = \XF::app()->options()->blacktea_steamauth_enabled_pages;

        if (!isset($enabledPages['active_players']) || $enabledPages['active_players'] == false) {
            return $this->redirect('');
        }

        $db = \XF::db();
        $userFinder = \XF::finder('XF:User');

        $perPage = 15;
        $page = $this->filterPage($params->page);
        $offset = ($page - 1) * $perPage;

        $count = $db->fetchOne('
            SELECT COUNT(distinct xsgu.user_id) FROM xf_steamauth_game_user AS xsgu
            WHERE xsgu.playtime_recent > 0
        ');

        $userPlaytimes = $db->fetchAllKeyed('
            SELECT xsgu.user_id, xuca.provider_key, SUM(xsgu.playtime_recent) AS total_playtime_recent, xsgu.last_updated FROM xf_steamauth_game_user AS xsgu
            JOIN xf_user_connected_account AS xuca
            ON xsgu.user_id = xuca.user_id
            AND xuca.provider = "steam"
            GROUP BY xsgu.user_id
            HAVING total_playtime_recent > 0
            ORDER BY total_playtime_recent DESC
            LIMIT ?, ?
        ', 'user_id', array($offset, $perPage));

        $users = $userFinder
            ->where('user_id', array_keys($userPlaytimes))
            ->fetch()
            ->toArray();

        foreach ($userPlaytimes as $userId => $userPlaytime) {
            $userPlaytimes[$userId]['recent_hours_formatted'] = $userPlaytime['total_playtime_recent'] <= 6 ? 0 : number_format($userPlaytime['total_playtime_recent'] / 60, 1);
        }

        $viewParams = [
            'users' => $users,
            'userPlaytimes' => $userPlaytimes,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $count
        ];

        return $this->view('BlackTea\SteamAuth:Active\Players', 'blacktea_steamauth_active_players', $viewParams);
    }

    public function actionActiveGames(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('steamauth', 'viewAnalytics')) {
            return $this->noPermission();
        }

        $enabledPages = \XF::app()->options()->blacktea_steamauth_enabled_pages;

        if (!isset($enabledPages['active_games']) || $enabledPages['active_games'] == false) {
            return $this->redirect('');
        }

        $db = \XF::db();

        $perPage = 15;
        $page = $this->filterPage($params->page);
        $offset = ($page - 1) * $perPage;

        $count = $db->fetchOne('
            SELECT COUNT(*) FROM (
                SELECT xsgu.game_id FROM xf_steamauth_game_user AS xsgu
                GROUP BY xsgu.game_id
                HAVING SUM(xsgu.playtime_recent) > 0
            ) AS data
        ');

        $games = $db->fetchAll('
            SELECT xsg.*, SUM(xsgu.playtime_recent) AS playtime FROM xf_steamauth_game AS xsg
            JOIN xf_steamauth_game_user AS xsgu
            ON xsg.game_id = xsgu.game_id
            GROUP BY xsgu.game_id
            HAVING playtime > 0
            ORDER BY playtime DESC, xsg.label ASC
            LIMIT ?, ?
        ', array($offset, $perPage));

        foreach ($games as $key => $game) {
            $games[$key]['playtime_formatted'] = $game['playtime'] <= 6 ? 0 : number_format($game['playtime'] / 60, 1);
        }

        $viewParams = [
            'games' => $games,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $count
        ];

        return $this->view('BlackTea\SteamAuth:Active\Games', 'blacktea_steamauth_active_games', $viewParams);
    }

    public function actionPlayedGames(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('steamauth', 'viewAnalytics')) {
            return $this->noPermission();
        }

        $enabledPages = \XF::app()->options()->blacktea_steamauth_enabled_pages;

        if (!isset($enabledPages['played_games']) || $enabledPages['played_games'] == false) {
            return $this->redirect('');
        }

        $db = \XF::db();

        $perPage = 15;
        $page = $this->filterPage($params->page);
        $offset = ($page - 1) * $perPage;

        $count = $db->fetchOne('
            SELECT COUNT(*) FROM (
                SELECT xsgu.game_id FROM xf_steamauth_game_user AS xsgu
                GROUP BY xsgu.game_id
                HAVING SUM(xsgu.playtime) > 0
            ) AS data
        ');

        $games = $db->fetchAll('
            SELECT xsg.*, SUM(xsgu.playtime) AS playtime, COUNT(*) AS user_count FROM xf_steamauth_game AS xsg
            JOIN xf_steamauth_game_user AS xsgu
            ON xsg.game_id = xsgu.game_id
            GROUP BY xsgu.game_id
            HAVING playtime > 0
            ORDER BY playtime DESC, xsg.label ASC
            LIMIT ?, ?
        ', array($offset, $perPage));

        foreach ($games as $key => $game) {
            $games[$key]['playtime_formatted'] = $game['playtime'] <= 6 ? 0 : number_format($game['playtime'] / 60, 1);
        }

        $viewParams = [
            'games' => $games,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $count
        ];

        return $this->view('BlackTea\SteamAuth:Played\Games', 'blacktea_steamauth_played_games', $viewParams);
    }

    public function actionOwnedGames(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->hasPermission('steamauth', 'viewAnalytics')) {
            return $this->noPermission();
        }

        $enabledPages = \XF::app()->options()->blacktea_steamauth_enabled_pages;

        if (!isset($enabledPages['owned_games']) || $enabledPages['owned_games'] == false) {
            return $this->redirect('');
        }

        $db = \XF::db();

        $perPage = 15;
        $page = $this->filterPage($params->page);
        $offset = ($page - 1) * $perPage;

        $count = $db->fetchOne('
          SELECT COUNT(*) FROM xf_steamauth_game
        ');

        $games = $db->fetchAll('
            SELECT xsg.*, SUM(playtime) AS playtime, COUNT(*) AS user_count FROM xf_steamauth_game AS xsg
            JOIN xf_steamauth_game_user AS xsgu
            ON xsg.game_id = xsgu.game_id
            GROUP BY xsgu.game_id
            ORDER BY user_count DESC, playtime DESC
            LIMIT ?, ?
        ', array($offset, $perPage));

        $viewParams = [
            'games' => $games,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $count
        ];

        return $this->view('BlackTea\SteamAuth:Owned\Games', 'blacktea_steamauth_owned_games', $viewParams);
    }

    /**
     * @param \XF\Entity\SessionActivity[] $activities
     * @return bool|mixed
     */
    public static function getActivityDetails(array $activities)
    {
        $router = \XF::app()->router('public');
        $viewingPhrase = \XF::phrase('blacktea_steamauth_viewing_analytics');

        $phraseMap = array(
            'ActivePlayers' => \XF::phrase('blacktea_steamauth_active_players'),
            'ActiveGames' => \XF::phrase('blacktea_steamauth_active_games'),
            'PlayedGames' => \XF::phrase('blacktea_steamauth_played_games'),
            'OwnedGames' => \XF::phrase('blacktea_steamauth_owned_games')
        );

        $output = array();

        foreach ($activities as $activity) {
            $action = $activity->getValue('controller_action');

            if (array_key_exists($action, $phraseMap)) {
                $params = $activity->getParams();
                $page = isset($params['page']) ? $params['page'] : 1;
                $route = 'steam/' . strtolower(preg_replace('~(?<=\\w)([A-Z])~', '-$1', $activity->getValue('controller_action')));
                $url = $router->buildLink($route, null, array('page' => $page));

                $output[] = array(
                    'description' => $viewingPhrase,
                    'title' => $phraseMap[$action],
                    'url' => $url
                );
            }
        }

        return $output;
    }

}