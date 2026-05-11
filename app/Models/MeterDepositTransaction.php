<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterDepositTransaction extends BaseModel
{
    use SoftDeletes;
    protected $connection = 'tenant';

    protected $table = 'meter_deposit_transactions';

    protected $fillable = [
        'fiscal_year_id',
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'voucher_no',
        'transaction_type',
        'service_charge',
        'amount',
        'upgraded_deposit_amount',
        'is_cancel',
    ];

    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date:Y-m-d',
        'meter_issue_id' => 'integer',
        'voucher_no' => 'string',
        'transaction_type' => 'integer',
        'service_charge' => 'decimal:2',
        'amount' => 'decimal:2',
        
    ];

    /**
     * Relationship: A transaction belongs to a member entry
     */
    public function meterIssue()
{
    return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
}

public function memberEntry()
{
    return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
}
    // public function memberEntry()
    // {
    //     return $this->belongsTo(MemberEntry::class, 'member_entry_id');
    // }
    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id');
    }
}
