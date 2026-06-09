<?php

namespace BlackTea\SteamAuth\Helper;

class Steam
{

    const API_USER = 'https://api.steampowered.com/ISteamUser';
    const API_PLAYER = 'https://api.steampowered.com/IPlayerService';

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string|null
     */
    protected $domain;

    /**
     * Steam constructor.
     * @param string $apiKey
     * @param null|string $domain
     */
    public function __construct($apiKey, $domain = null)
    {
        $this->apiKey = $apiKey;
        $this->domain = $domain;
    }

    /**
     * @param string $url
     * @param array $params
     * @return array|false
     * @throws \Exception
     */
    protected function request($url, $params = array())
    {
        $params['format'] = 'json';
        $fullUrl = $url . '?' . http_build_query($params);
        $response = \XF::app()->http()->reader()->get($fullUrl);

        if (!$response) {
            if (\XF::app()->options()->blacktea_steamauth_verbose_log) {
                throw new \Exception($url . ' did not respond.');
            }

            return false;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get Player Summaries
     * @param array $steamIds
     * @return array
     * @throws \Exception
     * @link https://developer.valvesoftware.com/wiki/Steam_Web_API#GetPlayerSummaries_.28v0002.29
     */
    public function getPlayerSummaries($steamIds = array())
    {
        if (count($steamIds) > 100) {
            // TODO ensure $steamIds is <= 100
        }

        $url = self::API_USER . '/GetPlayerSummaries/v0002/';

        $params = array(
            'key' => $this->apiKey,
            'steamids' => implode(',', $steamIds)
        );

        $response = $this->request($url, $params);

        if (!$response || empty($response['response'])) {
            throw new \Exception('Unable to fetch player summaries');
        }

        $players = $response['response']['players'];

        $personaStates = array(
            '0' => \XF::phrase('blacktea_steamauth_visibility_offline'),
            '1' => \XF::phrase('blacktea_steamauth_visibility_online'),
            '2' => \XF::phrase('blacktea_steamauth_visibility_busy'),
            '3' => \XF::phrase('blacktea_steamauth_visibility_away'),
            '4' => \XF::phrase('blacktea_steamauth_visibility_snooze'),
            '5' => \XF::phrase('blacktea_steamauth_visibility_trade'),
            '6' => \XF::phrase('blacktea_steamauth_visibility_play'),
            '7' => \XF::phrase('blacktea_steamauth_visibility_ingame')
        );

        foreach ($players as &$player) {
            if (isset($player['gameid'])) {
                $player['personastate'] = '7';
            }

            $player['personaname'] = htmlspecialchars($player['personaname']);
            $player['personavisibility'] = (string)$personaStates[$player['personastate']];
        }

        return $players;
    }

    public function getOwnedGames($steamId, $includeAppInfo = false, $includeFreeGames = true)
    {
        $url = self::API_PLAYER . '/GetOwnedGames/v0001/';

        $params = array(
            'key' => $this->apiKey,
            'steamid' => $steamId,
            'include_appinfo' => $includeAppInfo,
            'include_played_free_games' => $includeFreeGames
        );

        $response = $this->request($url, $params);

        if (!$response || !isset($response['response'])) {
            throw new \Exception('Unable to fetch owned games');
        }

        if (empty($response['response'])) {
            return array();
        }

        return $response['response']['games'];
    }

}