<?php

namespace App\Models;

use App\Observers\UpgradeMeterCapacityObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class UpgradeMeterCapacity extends BaseModel
{
    protected $connection = 'tenant';

    use HasFactory, SoftDeletes;


    protected $table = 'upgrade_meters';

    protected $fillable = [
        'fiscal_year_id',
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'voucher_no',
        'existing_capacity_id',
        'upgraded_capacity_id',
        'is_cancel',
    ];

    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'meter_issue_id' => 'integer',
        'voucher_no' => 'string',
        'existing_capacity_id ' => 'integer',
        'upgraded_capacity_id ' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];



    /**
     * Define the relationship with the MeterIssue model.
     */
   

    /**
     * Define the relationship with the DepositEntry model.
     */


    /**
     * Define the relationship with the OpeningMeterDepositEntry model.
     */
    public function openingMeterDepositEntry()
    {
        return $this->belongsTo(OpeningMeterDepositEntry::class, 'member_entry_id', 'member_entry_id');
    }

    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }

    public function existingMeter()
    {
        return $this->belongsTo(MasterSetup::class, 'existing_capacity_id', 'id');
    }

    public function upgradedMeter()
    {
        return $this->belongsTo(MasterSetup::class, 'upgraded_capacity_id', 'id');
    }
    public function member()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id', 'id')
            ->where('type', 2);
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
    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }




}
