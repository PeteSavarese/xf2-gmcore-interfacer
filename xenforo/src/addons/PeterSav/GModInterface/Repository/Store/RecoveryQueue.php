<?php

namespace PeterSav\GModInterface\Repository\Store;

use XF\Mvc\Entity\Repository;

class RecoveryQueue extends Repository {
    public function findAllOrdered(): \XF\Mvc\Entity\Finder {
        return $this->finder('PeterSav\\GModInterface:Store\\RecoveryQueue')
            ->order('transaction_date', 'ASC');
    }

    public function isDuplicateCaptureId(string $captureId): bool {
        return (bool)$this->finder('PeterSav\\GModInterface:Store\\RecoveryQueue')
            ->where('paypal_capture_id', $captureId)
            ->total();
    }

    public function createFromPaypal(string $captureId, string $payerEmail, int $transactionDate, array $paypalData): \PeterSav\GModInterface\Entity\Store\RecoveryQueue {
        $rankId = null;
        $amount = null;
        $purchaserUserId = null;

        $customId = $paypalData['custom_id'] ?? '';
        if (preg_match('/^rank_(\d+)_\d+$/', $customId, $m)) {
            $rankId = (int)$m[1];
        }

        if (!empty($paypalData['amount']['value'])) {
            $amount = (float)$paypalData['amount']['value'];
        }

        if (!empty($payerEmail)) {
            $user = \XF::app()->em()->findOne('XF:User', ['email' => $payerEmail]);
            if ($user) {
                $purchaserUserId = (int)$user->user_id;
            }
        }

        /** @var \PeterSav\GModInterface\Entity\Store\RecoveryQueue $entry */
        $entry = $this->em->create('PeterSav\\GModInterface:Store\\RecoveryQueue');
        $entry->paypal_capture_id = $captureId;
        $entry->payer_email = $payerEmail;
        $entry->transaction_date = $transactionDate;
        $entry->rank_id = $rankId;
        $entry->amount = $amount;
        $entry->purchaser_user_id = $purchaserUserId;
        $entry->paypal_raw = json_encode($paypalData);
        $entry->status = 'pending';
        $entry->save();

        return $entry;
    }
}
