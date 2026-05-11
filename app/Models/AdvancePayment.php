<?php

namespace App\Models;

use App\Observers\AdvancePaymentObserver;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvancePayment extends BaseModel{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'advance_payments';

    protected $fillable = [
        'fiscal_year_id',
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'voucher_no',
        'type',
        'amount',
        'deleted_at',
        'is_cancel',
    ];

    protected $casts = [
        'amount' => 'float',
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'meter_issue_id' => 'integer',
        'voucher_no' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Define the relationship with the OpeningMeterDepositEntry model.
     */
    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }

    public function member()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }

   
    public function cancel()
    {
        $this->update([
            'cancel_status' => 1
        ]);
    }


    public function meterIssueno()
    {
        return $this->belongsTo(MeterIssue::class, 'member_entry_id', 'member_entry_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id')
                    ->where('type', 4);
    }


}
