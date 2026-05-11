<?php

namespace App\Models;

use App\Observers\MeterInsuranceObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Blameable;

class MeterInsurance extends BaseModel
{
    protected $connection = 'tenant';

    use HasFactory, SoftDeletes;
    use Blameable;


    protected $table = 'meter_insurances';

    protected $fillable = [
        'fiscal_year_id',
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'voucher_no',
        'amount',
        'is_cancel',
    ];

    /**
     * Define the relationship with the MeterIssue model.
     */
    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }

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

    public function member()
    {
        return $this->hasOneThrough(
            MemberEntry::class, 
            MeterIssue::class,  
            'id',            
            'id',            
            'meter_issue_id',
            'member_entry_id'    
        );
    }


    // public function bank()
    // {
    //     return $this->belongsTo(MasterSetup::class, 'bank_id', 'id')->where('master_setup_type_id', 8);
    // }
   public function bank()
{
    return $this->belongsTo(AccountHead::class, 'bank_id', 'id')
                ->where('account_group_id', 10) 
                ->where('is_active', 1)
                ->whereNull('deleted_at');
}
    public function meterIssueno()
    {
        return $this->belongsTo(MeterIssue::class, 'member_entry_id', 'member_entry_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id', 'id')
                    ->where('type', 5);
    }



}
