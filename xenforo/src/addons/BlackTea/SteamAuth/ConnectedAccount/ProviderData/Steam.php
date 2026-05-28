<?php

namespace BlackTea\SteamAuth\ConnectedAccount\ProviderData;

use XF\ConnectedAccount\ProviderData\AbstractProviderData;

class Steam extends AbstractProviderData
{

    const PROVIDER_ID = 'steam';

    public function getDefaultEndpoint()
    {
        return 'users/@me';
    }

    public function getProviderKey()
    {
        return $this->storageState->getProviderToken()->getAccessToken();
    }

    public function getUsername()
    {
        return $this->getExtraParams()['name'];
    }

    public function getProfileUrl()
    {
        return $this->getExtraParams()['profileurl'];
    }

    public function getAvatarUrl()
    {
        return $this->getExtraParams()['avatar'];
    }

    public function getExtraParams()
    {
        $storageState = $this->storageState;
        $token = $storageState->getProviderToken();

        if (!$token) {
            throw new \Exception('An unexpected error occurred. Please try again later.');
        }

        $extraParams = $token->getExtraParams();

        if (empty($extraParams)) {
            $steamid = $token->getAccessToken();
            $steamApi = new \BlackTea\SteamAuth\Helper\Steam($storageState->getProvider()->getValue('options')['client_secret']);
            $playerSummaries = $steamApi->getPlayerSummaries(array($steamid));

            if (empty($playerSummaries)) {
                throw new \Exception('Unable to get Steam account summary');
            }

            $playerSummary = $playerSummaries[0];

            $token->setExtraParams(array(
                'token' => $token->getAccessToken(),
                'steamid64' => $playerSummary['steamid'],
                'name' => $playerSummary['personaname'],
                'profileurl' => $playerSummary['profileurl'],
                'avatar' => $playerSummary['avatarfull']
            ));
        }

        return $token->getExtraParams();
    }

}