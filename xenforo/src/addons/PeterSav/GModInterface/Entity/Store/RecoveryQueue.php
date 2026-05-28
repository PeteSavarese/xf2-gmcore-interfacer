<?php

namespace PeterSav\GModInterface\Entity\Store;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class RecoveryQueue extends Entity {
    public static function getStructure(Structure $structure) {
        $structure->table = 'gmod_store_recovery_queue';
        $structure->shortName = 'PeterSav\\GModInterface:Store\\RecoveryQueue';
        $structure->primaryKey = 'id';
        $structure->columns = [
            'id'                => ['type' => self::UINT, 'autoIncrement' => true],
            'paypal_capture_id' => ['type' => self::STR, 'maxLength' => 20, 'required' => true],
            'payer_email'       => ['type' => self::STR, 'maxLength' => 120, 'default' => ''],
            'transaction_date'  => ['type' => self::UINT, 'default' => 0],
            'rank_id'           => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'amount'            => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
            'purchaser_user_id' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'receiver_user_id'  => ['type' => self::UINT, 'nullable' => true, 'default' => null],
            'paypal_raw'        => ['type' => self::STR, 'default' => ''],
            'status'            => ['type' => self::STR, 'allowedValues' => ['pending', 'applied', 'skipped'], 'default' => 'pending'],
            'applied_at'        => ['type' => self::UINT, 'nullable' => true, 'default' => null],
        ];

        return $structure;
    }
}
