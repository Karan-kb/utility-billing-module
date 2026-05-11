<?php

namespace App\Events;

use App\Models\OtherIncomeReceipt;
use Illuminate\Queue\SerializesModels;

class OtherIncomeReceiptCompleted
{
    use SerializesModels;

    public $receipt;

    public function __construct(OtherIncomeReceipt $receipt)
    {
        $this->receipt = $receipt;
    }
}