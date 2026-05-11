<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningBalanceEntry extends  BaseModel
{
    use SoftDeletes;

    protected $connection = 'tenant'; 
    protected $table = 'opening_balance_entries';

    protected $fillable = [
        'fiscal_year_id',
        'account_head_id',
        'particulars',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];


    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }
}