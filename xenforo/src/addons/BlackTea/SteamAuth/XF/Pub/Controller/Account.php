<?php

namespace BlackTea\SteamAuth\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Account extends XFCP_Account
{

    protected function savePrivacyProcess(\XF\Entity\User $visitor)
    {
        $result = parent::savePrivacyProcess($visitor);

        $templater = $this->app()->get('templater');
        $escape = false;

        if ($templater->fnCanChangeSteamProfilePrivacy($templater, $escape)) {
            $values = $this->filter(array(
                'privacy' => array(
                    'allow_view_steam' => 'str'
                )
            ));

            $privacy = $visitor->getRelationOrDefault('Privacy');
            $result->setupEntityInput($privacy, $values['privacy']);
        }

        if ($templater->fnCanChangeSteamBannerPrivacy($templater, $escape)) {
            $values = $this->filter(array(
                'privacy' => array(
                    'allow_view_steam_banner' => 'str'
                )
            ));

            $privacy = $visitor->getRelationOrDefault('Privacy');
            $result->setupEntityInput($privacy, $values['privacy']);
        }

        return $result;
    }

    public function actionConnectedAccountDisassociate(ParameterBag $params)
    {
        $isSteam = $params->get('provider_id') == 'steam';
        $templater = $this->app()->get('templater');
        $escape = false;

        if ($isSteam && !$templater->fnCanDisassociateSteam($templater, $escape)) {
            return $this->redirect($this->buildLink('account/connected-accounts'));
        }

        $response = parent::actionConnectedAccountDisassociate($params);

        if ($isSteam) {
            $connectedAccount = $this->_getSteamConnnectedAccount();

            if ($connectedAccount && $connectedAccount->isDeleted()) {
                $userId = $connectedAccount->getValue('user_id');
                $steamId64 = $connectedAccount->getValue('provider_key');

                $repository = $this->repository('XF:ChangeLog');
                $repository->logChange('user', $userId, 'steamid_change', $steamId64, null, $userId);
            }
        }

        return $response;
    }

    protected function _getSteamConnnectedAccount()
    {
        $visitor = \XF::visitor();
        $connectedAccounts = $visitor->ConnectedAccounts;
        $provider = $this->assertProviderExists('steam');
        $connectedAccount = isset($connectedAccounts[$provider->provider_id]) ? $connectedAccounts[$provider->provider_id] : null;

        return $connectedAccount;
    }

}