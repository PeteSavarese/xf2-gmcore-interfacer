<?php

namespace BlackTea\SteamAuth\Admin\Controller;

use XF\Mvc\ParameterBag;

class Steam extends \XF\Admin\Controller\AbstractController
{

    public function actionMigrate()
    {
        $db = \XF::db();

        $connectedAccounts = $db->fetchAll('
          SELECT * FROM xf_user_connected_account
          WHERE provider = "steam"
          AND extra_data IN ("N;", "", "null")
        ');

        foreach ($connectedAccounts as $connectedAccount) {
            $update = array(
                'extra_data' => json_encode(array(
                    'token' => $connectedAccount['provider_key']
                ))
            );

            $where = 'provider = "steam" && user_id = "' . $connectedAccount['user_id'] . '"';

            $db->update('xf_user_connected_account', $update, $where);
        }

        $redirectUrl = $this->buildLink('index');
        return $this->redirect($redirectUrl);
    }

    public function actionList(ParameterBag $params)
    {
        $db = \XF::db();
        $userFinder = \XF::finder('XF:User');

        $perPage = 15;
        $page = $this->filterPage($params->page);
        $offset = ($page - 1) * $perPage;

        $criteria = $this->filter('criteria', 'array');
        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);

        $search = '%%';

        if (strlen($filter['text'])) {
            $search = $filter['prefix'] ? $filter['text'] . '%' : '%' . $filter['text'] . '%';
        }

        $connectedAccountsCount = $db->fetchOne("
          SELECT COUNT(*) FROM xf_user_connected_account AS xuca
          JOIN xf_user AS xu
          ON xuca.user_id = xu.user_id
          WHERE xuca.provider = 'steam'
          AND (xuca.provider_key LIKE ?
          OR xu.username LIKE ?
          OR xu.email LIKE ?)
        ", array($search, $search, $search));

        $connectedAccounts = $db->fetchAllKeyed("
          SELECT xuca.provider_key, xuca.user_id FROM xf_user_connected_account AS xuca
          JOIN xf_user AS xu
          ON xuca.user_id = xu.user_id
          WHERE xuca.provider = 'steam'
          AND (xuca.provider_key LIKE ?
          OR xu.username LIKE ?
          OR xu.email LIKE ?)
          LIMIT ?, ?
        ", 'user_id', array($search, $search, $search, $offset, $perPage));

        $users = $userFinder
            ->where('user_id', array_keys($connectedAccounts))
            ->fetch();

        $viewParams = array(
            'connectedAccounts' => $connectedAccounts,
            'total' => $connectedAccountsCount,
            'items' => $users,
            'perPage' => $perPage,
            'page' => $page,
            'criteria' => $criteria,
            'filter' => $filter['text']
        );

        return $this->view('BlackTea\SteamAuth:List', 'blacktea_steamauth_steam_list', $viewParams);
    }

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

    public function actionSupport(ParameterBag $params)
    {
        $app = \XF::app();

        if (!$app->request()->isPost()) {
            $viewParams = array();
            return $this->view('BlackTea\SteamAuth:Support', 'blacktea_steamauth_steam_support', $viewParams);
        }

        $em = $app->em();
        $options = $this->options();

        /** @var \XF\Repository\PermissionEntry $permissionRepository */
        $permissionRepository = $this->repository('XF:PermissionEntry');

        $input = $this->filter(array(
            'export_addon_setup' => 'bool',
            'export_addon_settings' => 'bool',
            'export_addon_permissions' => 'bool',
            'export_addon_users' => 'bool',
            'export_website_configuration' => 'bool',
            'export_enabled_addons' => 'bool'
        ));

        $data = array();

        if ($input['export_addon_setup']) {
            $provider = $em->find('XF:ConnectedAccountProvider', 'steam');
            $providerOptions = $provider->getValue('options');
            $data['setup'] = array(
                'client_id' => $providerOptions['client_id'],
                'client_secret' => str_repeat("x", strlen($providerOptions['client_secret']))
            );
        }

        if ($input['export_addon_settings']) {
            $data['settings'] = array(
                'blacktea_steamauth_force_registration' => $options->blacktea_steamauth_force_registration,
                'blacktea_steamauth_message_macro' => $options->blacktea_steamauth_message_macro,
                'blacktea_steamauth_message_macro_steamid' => $options->blacktea_steamauth_message_macro_steamid,
                'blacktea_steamauth_user_banner' => $options->blacktea_steamauth_user_banner,
                'blacktea_steamauth_enabled_pages' => $options->blacktea_steamauth_enabled_pages,
                'blacktea_steamauth_game_update_frequency' => $options->blacktea_steamauth_game_update_frequency,
                'blacktea_steamauth_user_update_limit' => $options->blacktea_steamauth_user_update_limit,
                'blacktea_steamauth_api_sleep' => $options->blacktea_steamauth_api_sleep,
                'blacktea_steamauth_verbose_log' => $options->blacktea_steamauth_verbose_log,
            );
        }

        if ($input['export_addon_permissions']) {
            $data['permissions'] = array();
            $groups = $this->finder('XF:UserGroup')->order('title')->fetch();
            /** @var \XF\Entity\UserGroup $group */
            foreach ($groups as $group) {
                $permissions = $permissionRepository->getGlobalUserGroupPermissionEntries($group->getEntityId());
                if (isset($permissions['steamauth'])) {
                    $data['permissions'][$group->title] = $permissions['steamauth'];
                }
            }
        }

        if ($input['export_addon_users']) {
            $db = \XF::db();
            $connectionAccounts = $db->fetchAll('SELECT * FROM xf_user_connected_account WHERE provider = "steam"');
            foreach ($connectionAccounts as $connectedAccount) {
                $data['users'][$connectedAccount['user_id']] = $connectedAccount;
            }
            $users = $app->em()->findByIds('XF:User', array_keys($data['users']), array('Privacy'));
            foreach ($users as $user) {
                $privacy = $user->getRelation('Privacy');
                $data['users'][$user->getEntityId()]['privacy'] = array(
                    'allow_view_steam' => $privacy->allow_view_steam,
                    'allow_view_steam_banner' => $privacy->allow_view_steam_banner
                );
            }
        }

        if ($input['export_website_configuration']) {
            $data['website_configuration'] = array(
                'currentVersionId' => $options->currentVersionId,
                'boardUrl' => $options->boardUrl,
                'homePageUrl' => $options->homePageUrl
            );
        }

        if ($input['export_enabled_addons']) {
            $data['addons'] = array();
            foreach ($this->app->addOnManager()->getAllAddOns() as $id => $addon) {
                $data['addons'][$id] = array(
                    'versionId' => $addon->getJson()['version_id'],
                    'versionString' => $addon->getJson()['version_string'],
                    'canEdit' => $addon->canEdit(),
                    'canInstall' => $addon->canInstall(),
                    'canUninstall' => $addon->canUninstall(),
                    'canUpgrade' => $addon->canUpgrade(),
                    'canRebuild' => $addon->canRebuild()
                );
            }
        }

        $content = \json_encode($data);
        $app->response()->header('Content-Description', 'File Transfer');
        $app->response()->header('Content-Type', 'application/octet-stream');
        $app->response()->header('Content-Disposition', 'attachment; filename="steamauth_export.json"');
        $app->response()->header('Expires', '0');
        $app->response()->header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $app->response()->header('Pragma', 'public');
        $app->response()->header('Content-Length', strlen($content));
        $app->response()->sendHeaders();
        echo $content;
        die;
    }

}