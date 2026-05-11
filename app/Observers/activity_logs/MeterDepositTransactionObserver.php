<?php

namespace App\Observers\activity_logs;

use App\Observers\BaseObserver;
use App\Models\MeterDepositTransaction;

class MeterDepositTransactionObserver extends BaseObserver
{
    public function __construct()
    {
        parent::__construct(9); 
    }

    public function created($transaction)
    {
        $action = $transaction->transaction_type === 0 ? 'deposit_entry_created' : 'deposit_return_created';
        $this->log($action, $transaction);
    }

    public function updated($transaction)
    {
        $meaningfulChanges = array_diff_key(
            $transaction->getChanges(),
            ['updated_at' => '', 'deleted_at' => '']
        );

        if (empty($meaningfulChanges)) return;

        $action = $transaction->transaction_type === 0 ? 'deposit_entry_updated' : 'deposit_return_updated';
        $this->log($action, $transaction);
    }

    public function deleted($transaction)
    {
        $action = $transaction->transaction_type === 0 ? 'deposit_entry_deleted' : 'deposit_return_deleted';
        $this->log($action, $transaction);
    }
}
