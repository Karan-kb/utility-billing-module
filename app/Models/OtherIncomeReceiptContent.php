<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherIncomeReceiptContent extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'other_income_receipt_contents';

    protected static $useFiscalYear = false;

    use SoftDeletes;

    protected $fillable = [
        'other_income_receipt_id',
        'account_head_id',
        'charge_amount',
        'amount',
    ];

    protected $casts = [
        'other_income_receipt_id' => 'integer',
        'account_head_id' => 'integer',
        'charge_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function otherIncomeReceipt()
    {
        return $this->belongsTo(OtherIncomeReceipt::class, 'other_income_receipt_id');
    }

    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id', 'id');
    }


}
