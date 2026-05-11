<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TenantModel;

class BankVoucher extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'bank_vouchers';
    protected $fillable = [
        'type',
        'date_in_bs',
        'voucher_no',
        'amount',
        'balance_after',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'type' => 'integer',
    ];
}
