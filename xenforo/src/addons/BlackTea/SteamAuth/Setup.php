<?php

namespace BlackTea\SteamAuth;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    const TABLE_CONNECTED_ACCOUNT_PROVIDER = 'xf_connected_account_provider';
    const TABLE_USER_CONNECTED_ACCOUNT = 'xf_user_connected_account';
    const TABLE_USER_PRIVACY = 'xf_user_privacy';
    const TABLE_STEAMAUTH_GAME = 'xf_steamauth_game';
    const TABLE_STEAMAUTH_GAME_USER = 'xf_steamauth_game_user';

    const PROVIDER_ID = 'steam';
    const PROVIDER_CLASS = 'BlackTea\\SteamAuth:Provider\\Steam';
    const PROVIDER_DISPLAY_ORDER = '80';

    const PRIVACY_VIEW_FLAG = 'allow_view_steam';
    const PRIVACY_VIEW_BANNER_FLAG = 'allow_view_steam_banner';

    public function installStep1()
    {
        $this
            ->db()
            ->insert(
                self::TABLE_CONNECTED_ACCOUNT_PROVIDER,
                array(
                    'provider_id' => self::PROVIDER_ID,
                    'provider_class' => self::PROVIDER_CLASS,
                    'display_order' => self::PROVIDER_DISPLAY_ORDER,
                    'options' => ''
                )
            );
    }

    public function installStep2()
    {
        $this
            ->schemaManager()
            ->alterTable(self::TABLE_USER_PRIVACY, function (Alter $table) {
                $table
                    ->addColumn(self::PRIVACY_VIEW_FLAG, 'enum')
                    ->values(array('everyone', 'members', 'none'))
                    ->setDefault('everyone');
            });
    }

    public function installStep3()
    {
        $this->_migrateXf1();
    }

    public function installStep4()
    {
        $this->applyGlobalPermission('steamauth', 'changePrivacy');
        $this->applyGlobalPermission('steamauth', 'disassociate');
    }

    public function installStep5()
    {
        $this
            ->schemaManager()
            ->alterTable(self::TABLE_USER_PRIVACY, function (Alter $table) {
                $table
                    ->addColumn(self::PRIVACY_VIEW_BANNER_FLAG, 'enum')
                    ->values(array('everyone', 'members', 'none'))
                    ->setDefault('everyone');
            });

        $this->applyGlobalPermission('steamauth', 'viewBanner');
    }

    public function installStep6()
    {
        $this
            ->schemaManager()
            ->createTable(self::TABLE_STEAMAUTH_GAME, function (Create $table) {
                $table->addColumn('game_id', 'int', 11)
                    ->primaryKey()
                    ->unsigned(true)
                    ->autoIncrement(false)
                    ->nullable(false)
                    ->comment('Game ID');

                $table->addColumn('label', 'varchar', 64)
                    ->nullable(false)
                    ->comment('Label');

                $table->addColumn('image', 'varchar', 255)
                    ->nullable(true)
                    ->comment('Image');

                $table->addColumn('url', 'varchar', 255)
                    ->nullable(true)
                    ->comment('URL');

                $table->comment('SteamAuth Games');
            });

        $this
            ->schemaManager()
            ->createTable(self::TABLE_STEAMAUTH_GAME_USER, function (Create $table) {
                $table->addColumn('game_id', 'int', 11)
                    ->unsigned(true)
                    ->nullable(false)
                    ->comment('Game ID');

                $table->addColumn('user_id', 'int', 10)
                    ->unsigned(true)
                    ->nullable(false)
                    ->comment('Game ID');

                $table->addColumn('playtime', 'int', 11)
                    ->unsigned(true)
                    ->nullable(false)
                    ->setDefault(0)
                    ->comment('Playtime');

                $table->addColumn('playtime_recent', 'int', 11)
                    ->unsigned(true)
                    ->nullable(false)
                    ->setDefault(0)
                    ->comment('Recent Playtime');

                $table->addColumn('last_updated', 'int', 11)
                    ->unsigned(true)
                    ->nullable(false)
                    ->comment('Last updated timestamp');

                $table->addIndex('game_id_user_id')
                    ->type('unique')
                    ->addColumn('game_id')
                    ->addColumn('user_id');

                $table->comment('SteamAuth User Games');
            });
    }

    public function installStep7()
    {
        $this->db()->delete(self::TABLE_STEAMAUTH_GAME_USER, 'user_id NOT IN (SELECT user_id FROM xf_user)');
        $this->schemaManager()->alterTable(self::TABLE_STEAMAUTH_GAME_USER, function (Alter $table) {
            $table->changeColumn('user_id')->comment('User ID');
            $table->addIndex('game_id')->type('key')->addColumn('game_id');
            $table->addIndex('last_updated')->type('key')->addColumn('last_updated');
        });
        $this->applyGlobalPermission('steamauth', 'viewAnalytics');
    }

    public function upgrade10401070Step1()
    {
        $this->_migrateXf1();
    }

    public function upgrade10200070Step1()
    {
        $this->installStep2();
    }

    public function upgrade10501070Step1()
    {
        $this->installStep4();
    }

    public function upgrade10501070Step2()
    {
        $this->installStep5();
    }

    public function upgrade10701010Step1()
    {
        $this->installStep6();
    }

    public function upgrade10709070Step1()
    {
        $this->installStep7();
    }

    public function uninstallStep1()
    {
        $this
            ->db()
            ->delete(
                self::TABLE_CONNECTED_ACCOUNT_PROVIDER,
                'provider_id = \'' . self::PROVIDER_ID . '\''
            );
    }

    public function uninstallStep2()
    {
        $this
            ->db()
            ->delete(
                self::TABLE_USER_CONNECTED_ACCOUNT,
                'provider_key = \'' . self::PROVIDER_ID . '\''
            );
    }

    public function uninstallStep3()
    {
        $this
            ->schemaManager()
            ->alterTable(self::TABLE_USER_PRIVACY, function (Alter $table) {
                $table->dropColumns([self::PRIVACY_VIEW_FLAG]);
            });
    }

    public function uninstallStep4()
    {
        $this
            ->schemaManager()
            ->dropTable(self::TABLE_STEAMAUTH_GAME);

        $this
            ->schemaManager()
            ->dropTable(self::TABLE_STEAMAUTH_GAME_USER);
    }

    protected function _migrateXf1()
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
    }

}