<?php

namespace App\Observers\activity_logs;

use App\Observers\BaseObserver;

class ShareTransactionObserver extends BaseObserver
{
    public function __construct()
    {
        parent::__construct(14); 
    }

    public function created($transaction)
    {
        $action = $transaction->transaction_type === 0 ? 'share_entry_created' : 'share_return_created';
        $this->log($action, $transaction);
    }

    public function updated($transaction)
    {
        $meaningfulChanges = array_diff_key(
            $transaction->getChanges(),
            ['updated_at' => '', 'deleted_at' => '']
        );

        if (empty($meaningfulChanges)) return;

        $action = $transaction->transaction_type === 0 ? 'share_entry_updated' : 'share_return_updated';
        $this->log($action, $transaction);
    }

    public function deleted($transaction)
    {
        $action = $transaction->transaction_type === 0 ? 'share_entry_deleted' : 'share_return_deleted';
        $this->log($action, $transaction);
    }
}
