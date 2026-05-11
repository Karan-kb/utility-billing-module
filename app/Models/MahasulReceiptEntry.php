<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use App\Observers\MahasulReceiptObserver;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Blameable;

class MahasulReceiptEntry extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes, Blameable;

    protected $table = 'mahasul_receipts';


    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'uuid',
        'is_synced',
        'dummy_voucher',
        'meter_reading_entry_id',
        'voucher_no',
        'fine_amount',
        'demand_charge',
        'subsidy_charge',
        'service_charge',
        'other_charge',
        'rebate_amount',
        'total_due_amount',
        'paid_amount',
        'total_amount',
        'unit_amount',
        'discount_amount',
        'black_list_charge',
        'is_cancel',
        'advance_status',
        'advance_payment',
        'fiscal_year_id',
    ];



    protected $casts = [

        'cash_amount' => 'decimal:2',
        'bank_amount' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'rebate_amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'demand_charge' => 'decimal:2',
        'subsidy_charge' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'other_charge' => 'decimal:2',
        'black_list_charge' => 'decimal:2',
        'total_due_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'unit_amount' => 'decimal:2',
    ];


    // Relationships
    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }


    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }

    public function meterIssueNo()
    {
        return $this->belongsTo(MeterIssue::class, 'member_entry_id', 'member_entry_id');
    }
    public function area()
    {
        return $this->belongsTo(MasterSetup::class, 'area_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id')
            ->where('type', 11);
    }
    // public function bank()
    // {
    //     return $this->belongsTo(MasterSetup::class, 'bank_id')
    //         ->where('master_setup_type_id', 8);
    // }

public function bank()
{
    return $this->belongsTo(AccountHead::class, 'bank_id')
                ->where('account_group_id', 10) // bank accounts
                ->where('is_active', 1)
                ->whereNull('deleted_at');
}
}
