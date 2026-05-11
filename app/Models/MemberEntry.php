<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\Blameable;

class MemberEntry extends BaseModel
{
    protected $connection = 'tenant';       

     use softDeletes, HasFactory;

    use Blameable;

    protected $table = 'member_entries';

   


    protected $fillable = [
        'member_no',
        'customer_name_en',
        'fiscal_year_id',
        'customer_name_np',
        'is_disable',
        'citizenship_no',
        'gender',
        'occupation_id',
        'pan_no',
        'father_or_husband_name',
        'grandfather_or_father_in_law_name',
        'house_owner_name',
        'contact_no',
        'province_id',
        'district_id',
        'is_blacklisted',
        'municipality_id',
        'ward_no',
        'area_id',
        'house_no',
        'location_description',
        'floor',
        'wiring_person_id',
        'customer_photo',
        'citizenship_front',
        'citizenship_back',
        'is_active'
    ];
    protected $casts = [
        'is_disable' => 'boolean',
    ];


    public function occupation()
    {
        return $this->belongsTo(MasterSetup::class, 'occupation_id', 'id');
    }

    public function area()
    {
        return $this->belongsTo(MasterSetup::class, 'area_id', 'id');
    }
    public function wiringPerson()
    {
        return $this->belongsTo(WiringPerson::class, 'wiring_person_id', 'id');
    }

    public function meterIssues()
    {
        return $this->hasMany(MeterIssue::class, 'member_entry_id', 'id');
    }

    public function customerTransactions()
    {
        return $this->hasMany(CustomerTransaction::class, 'member_entry_id', 'id');
    }

    public function province()
    {
        return $this->belongsTo(TenantProvince::class, 'province_id');
    }

    public function district()
    {
        return $this->belongsTo(TenantDistrict::class, 'district_id');
    }

    public function municipality()
    {
        return $this->belongsTo(TenantMunicipality::class, 'municipality_id');
    }
    public function openingMeterDepositEntry()
    {
        return $this->hasOne(OpeningMeterDepositEntry::class, 'member_entry_id', 'id');
    }

    public function latestUpgradeMeter()
    {
        return $this->hasOneThrough(
            UpgradeMeterCapacity::class,
            MeterIssue::class,
            'member_entry_id',  
            'meter_issue_id',   
            'id',                
            'id'                 
        )
            ->where('upgrade_meters.is_cancel', 0)
            ->latestOfMany(); 
    }


public function nameTransferAsPrevious()
{
    return $this->hasOne(NameTransferEntry::class, 'previous_member_entry_id', 'id');
}
}
