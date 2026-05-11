<?php
namespace App\Observers\activity_logs;

use App\Observers\BaseObserver;

class FineObserver extends BaseObserver
{
    public function __construct()
    {
        parent::__construct(16); // 2 = module_type for Fine
    }
}
