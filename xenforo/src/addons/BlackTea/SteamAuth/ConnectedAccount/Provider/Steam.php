<?php

namespace BlackTea\SteamAuth\ConnectedAccount\Provider;

use XF\Entity\ConnectedAccountProvider;
use XF\ConnectedAccount\Http\HttpResponseException;
use XF\ConnectedAccount\Provider\AbstractProvider;

class Steam extends AbstractProvider
{

    public function getOAuthServiceName()
    {
        return 'BlackTea\SteamAuth:Service\Steam';
    }

    public function getProviderDataClass()
    {
        return 'BlackTea\SteamAuth:ProviderData\Steam';
    }

    public function getDefaultOptions()
    {
        return [
            'client_id' => '',
            'client_secret' => ''
        ];
    }

    public function getOAuthConfig(ConnectedAccountProvider $provider, $redirectUri = null)
    {
        return [
            'key' => $provider->options['client_id'],
            'secret' => $provider->options['client_secret'],
            'scopes' => [],
            'redirect' => $redirectUri ?: $this->getRedirectUri($provider)
        ];
    }

    public function parseProviderError(HttpResponseException $e, &$error = null)
    {
        $response = json_decode($e->getResponseContent(), true);

        if (is_array($response) && isset($response['error']['message'])) {
            $e->setMessage($response['error']['message']);
        }

        parent::parseProviderError($e, $error);
    }

	public function getIconClass(): ?string
	{
		return 'fab fa-steam';
	}
}