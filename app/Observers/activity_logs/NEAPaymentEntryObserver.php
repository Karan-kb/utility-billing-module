<?php

namespace App\Observers\activity_logs;

use App\Observers\BaseObserver;

class NEAPaymentEntryObserver extends BaseObserver
{
    public function __construct()
    {
        parent::__construct(4); 
    }
}
