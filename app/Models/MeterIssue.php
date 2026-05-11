<?php

namespace App\Models;

use App\Http\Controllers\Backend\OpeningMeterDepositEntryController;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Blameable;

class MeterIssue extends BaseModel
{
    protected $connection = 'tenant';
   
     use softDeletes, HasFactory, Blameable;
    protected $table = 'meter_issues';

    protected static $useFiscalYear = false;

    protected $fillable = [
        'member_entry_id',
        'transferred_to',
        'phase_id',    
        'capacity_id',
        'purpose_id',
        'transformer_id',
        'meter_no',
        'meter_start_no',
        'issue_date_bs',
        'construct_company',
        'issue_meter_capacity',
        'reading_seal_no',
        'terminal_seal_no',
        'meter_box_seal_no',
        'pole_no',
        'pole_distance',
        'area_id',
        'is_active',
        'meter_issue_record',
        'fiscal_year_id',
        'issue_date_ad'
    ];

    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }

    public function meterReadingEntries()
    {
        return $this->hasMany(MeterReadingEntry::class, 'meter_issue_id');
    }

    public static function getMemberByMeterIssueId($meter_issue_id)
    {
        $meter_issue = self::with('memberEntry')->find($meter_issue_id);
        return $meter_issue ? $meter_issue->memberEntry : null;
    }


    public function transformer()
    {
        return $this->belongsTo(MasterSetup::class, 'transformer_id', 'id');
    }

    public function demandPhase()
    {
        return $this->belongsTo(MasterSetup::class, 'phase_id', 'id');
    }

    public function demandCapacity()
    {
        return $this->belongsTo(MasterSetup::class, 'capacity_id', 'id');
    }



    public function readingArea()
    {
        return $this->belongsTo(MasterSetup::class, 'area_id', 'id');
    }

    public function purposeType()
    {
        return $this->belongsTo(MasterSetup::class, 'purpose_id', 'id');
    }

    public function mahasulReceipts()
    {
        return $this->hasMany(MahasulReceiptEntry::class, 'member_entry_id', 'member_entry_id')
            ->whereNull('deleted_at');
    }


    public function openingMeterDepositEntry()
    {
        return $this->hasOne(OpeningMeterDepositEntry::class, 'meter_issue_id', 'id');
    }

    public function openingMahasulBalanceEntry()
    {
        return $this->hasOne(OpeningMahasulBalanceEntry::class, 'meter_issue_id', 'id')
            ->where('is_active', 1);
    }
public function member()
{
    return $this->belongsTo(MemberEntry::class,'member_entry_id','id')->withTrashed();
}
public function nameTransferAsPrevious()
{
    return $this->hasOne(NameTransferEntry::class, 'previous_member_entry_id', 'member_entry_id');
}
}
