<?php

namespace App\Observers\activity_logs;

use App\Observers\BaseObserver;

class MeterIssueObserver extends BaseObserver
{
    public function __construct()
    {
        parent::__construct(10); 
    }
}
