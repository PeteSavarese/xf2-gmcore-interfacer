<?php

namespace BlackTea\SteamAuth\ConnectedAccount\Service;

use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Http\Uri\UriInterface;

class Steam extends AbstractService
{

    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        $scopes = array(),
        UriInterface $baseApiUri = null
    )
    {
        parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri);

        if (null === $baseApiUri) {
            $this->baseApiUri = new Uri('https://steamcommunity.com/openid/login');
        }
    }

    public function getAuthorizationUri(array $additionalParameters = array())
    {
        $parameters = array_merge(
            $additionalParameters,
            array(
                'openid.ns' => 'http://specs.openid.net/auth/2.0',
                'openid.mode' => 'checkid_setup',
                'openid.return_to' => $this->credentials->getCallbackUrl(),
                'openid.realm' => $this->credentials->getConsumerId(),
                'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
                'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select'
            )
        );

        $url = $this->getAuthorizationEndpoint();

        foreach ($parameters as $key => $val) {
            $url->addToQuery($key, $val);
        }

        return $url;
    }

    public function getAuthorizationEndpoint()
    {
        return new Uri('https://steamcommunity.com/openid/login');
    }

    public function getAccessTokenEndpoint()
    {
        return new Uri('https://steamcommunity.com/openid/login');
    }

    protected function getAuthorizationMethod()
    {
        return static::AUTHORIZATION_METHOD_HEADER_BEARER;
    }

    public function requestAccessToken($code, $state = null)
    {
        $mode = filter_input(INPUT_GET, 'openid_mode', FILTER_SANITIZE_URL);

        if ($mode == 'error') {
            $error = filter_input(INPUT_GET, 'openid_error', FILTER_SANITIZE_URL);
            $exception = new \Exception('Response from Steam: ' . $error);
            \XF::logException($exception, true);
            throw $exception;
        }

        $params = array(
            'openid.assoc_handle' => filter_input(INPUT_GET, 'openid_assoc_handle', FILTER_SANITIZE_URL),
            'openid.signed' => filter_input(INPUT_GET, 'openid_signed', FILTER_SANITIZE_URL),
            'openid.sig' => filter_input(INPUT_GET, 'openid_sig', FILTER_SANITIZE_URL),
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'check_authentication'
        );

        $signedItems = filter_input(INPUT_GET, 'openid_signed', FILTER_SANITIZE_URL);
        $signedItems = explode(',', $signedItems);

        foreach ($signedItems as $item) {
            $param = 'openid_' . str_replace('.', '_', $item);
            $value = filter_input(INPUT_GET, $param, FILTER_SANITIZE_URL);
            $params['openid.' . $item] = function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() ? stripslashes($value) : $value;
        }

        $responseBody = $this->httpClient->retrieveResponse($this->getAccessTokenEndpoint(), $params, $this->getExtraOAuthHeaders());

        $token = $this->parseAccessTokenResponse($responseBody);
        $this->storage->storeAccessToken($this->service(), $token);

        return $token;
    }

    protected function parseAccessTokenResponse($responseBody)
    {
        if (strpos($responseBody, 'is_valid:true') === false) {
            throw new TokenResponseException('Unable to parse response.');
        }

        $id = filter_input(INPUT_GET, 'openid_identity', FILTER_SANITIZE_URL);
        $ptn = "/^https?:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
        preg_match($ptn, $id, $matches);
        $steamId = $matches[1];

        $token = new StdOAuth2Token();
        $token->setAccessToken($steamId);

        $steamApi = new \BlackTea\SteamAuth\Helper\Steam($this->credentials->getConsumerSecret());
        $playerSummaries = $steamApi->getPlayerSummaries(array($steamId));

        if (empty($playerSummaries)) {
            throw new \Exception('Unable to get Steam account summary');
        }

        $playerSummary = $playerSummaries[0];

        $token->setExtraParams(array(
            'token' => $steamId,
            'steamid64' => $playerSummary['steamid'],
            'name' => $playerSummary['personaname'],
            'profileurl' => $playerSummary['profileurl'],
            'avatar' => $playerSummary['avatarfull']
        ));

        return $token;
    }

    public function request($path, $method = 'GET', $body = null, array $extraHeaders = array())
    {
        $service = $this->service();
        $accessToken = $this->storage->retrieveAccessToken($service);
        $steamId = $accessToken->getAccessToken();

        $steamApi = new \BlackTea\SteamAuth\Helper\Steam($this->credentials->getConsumerSecret());

        $playerSummaries = $steamApi->getPlayerSummaries(array($steamId));
        $playerSummary = $playerSummaries[0];

        return json_encode($playerSummary);
    }

}
