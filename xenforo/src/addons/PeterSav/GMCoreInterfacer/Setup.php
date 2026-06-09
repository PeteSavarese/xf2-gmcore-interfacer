<?php

namespace PeterSav\GModInterface;

use XF\Db\Schema\Create;
use XF\Db\Schema\Alter;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup {
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	public function install(array $stepParams = []) {
		$this->schemaManager()->createTable('gmod_store_transactions', function (Create $table) {
			$table->addColumn('transaction_id', 'int')->autoIncrement()->primaryKey();
			$table->addColumn('user_id', 'int')->nullable();
			$table->addColumn('transaction_time', 'int')->nullable();
			$table->addColumn('transaction_log', 'text')->nullable();
			$table->engine('InnoDB');
		});

		$this->schemaManager()->createTable('gmod_store_rank_groups', function (Create $table) {
			$table->addColumn('upgrade_id', 'int')->unsigned()->autoIncrement()->primaryKey();
			$table->addColumn('title', 'varchar', 35);
			$table->addColumn('description', 'text');
			$table->addColumn('rank_image', 'varchar', 100);
			$table->addColumn('rank_priority', 'int');
			$table->addColumn('group_id', 'int')->unsigned();
			$table->addColumn('price', 'decimal', '10,2')->unsigned();
			$table->addColumn('length', 'tinyint')->unsigned()->setDefault(0);
			$table->addColumn('length_unit', 'enum')->values(['day', 'month', 'year', ''])->setDefault('');
			$table->addColumn('can_purchase', 'tinyint')->unsigned();
			$table->engine('InnoDB');
		});

		$this->schemaManager()->createTable('gmod_store_rank_active', function (Create $table) {
			$table->addColumn('upgrade_id', 'int')->unsigned()->autoIncrement()->primaryKey();
			$table->addColumn('user_id', 'int')->unsigned();
			$table->addColumn('steamid64', 'bigint')->unsigned();
			$table->addColumn('transaction_id', 'int')->unsigned();
			$table->addColumn('upgrade_group_id', 'int')->unsigned();
			$table->addColumn('gift', 'int')->unsigned();
			$table->addColumn('purchased', 'int')->unsigned();
			$table->addColumn('expires', 'int')->unsigned();
			$table->engine('InnoDB');
		});

		$this->schemaManager()->createTable('gmod_store_rank_expired', function (Create $table) {
			$table->addColumn('upgrade_id', 'int')->unsigned()->autoIncrement()->primaryKey();
			$table->addColumn('user_id', 'int')->unsigned();
			$table->addColumn('steamid64', 'bigint')->unsigned();
			$table->addColumn('transaction_id', 'int')->unsigned();
			$table->addColumn('upgrade_group_id', 'int')->unsigned();
			$table->addColumn('gift', 'int')->unsigned();
			$table->addColumn('purchased', 'int')->unsigned();
			$table->addColumn('expires', 'int')->unsigned();
			$table->engine('InnoDB');
		});
	}

	public function uninstall(array $stepParams = []) {
		$this->schemaManager()->dropTable('gmod_store_transactions');
		$this->schemaManager()->dropTable('gmod_store_rank_groups');
		$this->schemaManager()->dropTable('gmod_store_rank_active');
		$this->schemaManager()->dropTable('gmod_store_rank_expired');
		$this->schemaManager()->dropTable('gmod_store_recovery_queue');
	}

	public function upgrade1013000Step1(array $stepParams = [])
	{
		$this->schemaManager()->createTable('gmod_store_recovery_queue', function (Create $table) {
			$table->addColumn('id', 'int')->unsigned()->autoIncrement()->primaryKey();
			$table->addColumn('paypal_capture_id', 'varchar', 20);
			$table->addColumn('payer_email', 'varchar', 120);
			$table->addColumn('transaction_date', 'int')->unsigned()->setDefault(0);
			$table->addColumn('rank_id', 'int')->unsigned()->nullable();
			$table->addColumn('amount', 'decimal', '8,2')->nullable();
			$table->addColumn('purchaser_user_id', 'int')->unsigned()->nullable();
			$table->addColumn('receiver_user_id', 'int')->unsigned()->nullable();
			$table->addColumn('paypal_raw', 'mediumtext')->nullable();
			$table->addColumn('status', 'enum')->values(['pending', 'applied', 'skipped'])->setDefault('pending');
			$table->addColumn('applied_at', 'int')->unsigned()->nullable();
			$table->addUniqueKey('paypal_capture_id');
			$table->engine('InnoDB');
		});
	}
}