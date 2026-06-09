<?php

namespace BlackTea\SteamAuth\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Register extends XFCP_Register
{

    public function actionConnectedAccountAssociate(ParameterBag $params)
    {
        $isSteam = $params->get('provider_id') == 'steam';

        $response = parent::actionConnectedAccountAssociate($params);

        if ($isSteam) {
            $visitor = \XF::visitor();

            if (!$visitor->user_id) {
                $redirect = $this->getDynamicRedirect(null, false);
                return $this->redirect($redirect);
            }

            $provider = $this->assertProviderExists($params->provider_id);
            $handler = $provider->getHandler();
            $storageState = $handler->getStorageState($provider, $visitor);
            $providerData = $handler->getProviderData($storageState);
            $connectedAccountRepo = $this->repository('XF:ConnectedAccount');

            $connectedAccount = $connectedAccountRepo->getUserConnectedAccountFromProviderData($providerData);

            if ($connectedAccount) {
                $steamId64 = $connectedAccount->getValue('provider_key');
                $userId = $connectedAccount->getValue('user_id');

                $repository = $this->repository('XF:ChangeLog');
                $repository->logChange('user', $userId, 'steamid_change', null, $steamId64, $userId);
            }
        }

        return $response;
    }

    public function actionRegister()
    {
        if ($this->options()->blacktea_steamauth_force_registration == "1") {
            return $this->redirect($this->buildLink('index'));
        }

        return parent::actionRegister();
    }

}