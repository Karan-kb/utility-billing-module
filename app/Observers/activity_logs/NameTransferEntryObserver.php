<?php

namespace App\Observers\activity_logs;

use App\Observers\BaseObserver;

class NameTransferEntryObserver extends BaseObserver
{
    public function __construct()
    {
        parent::__construct(11);
    }
}
