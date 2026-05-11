<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherIncomeReceipt extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'other_income_receipts';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'voucher_no',
        'meter_issue_id',
        'fiscal_year_id',
        'amount',
        'is_cancel',
        
    ];

    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'voucher_no' => 'string',
        'meter_issue_id' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

    ];

    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }
   

    /** Bank */
    // public function bank()
    // {
    //     return $this->belongsTo(MasterSetup::class, 'bank_id', 'id');
    // }
    public function bank()
{
    return $this->belongsTo(AccountHead::class, 'bank_id', 'id')
                ->where('account_group_id', 10) // bank accounts
                ->where('is_active', 1)
                ->whereNull('deleted_at');
}
    public function incomeHeadsContent()
    {
        return $this->hasMany(OtherIncomeReceiptContent::class, 'other_income_receipt_id');
    }

    
    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id')
            ->where('type', 6);
    }





}
