<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Blameable;

class ChangeMeter extends BaseModel
{
    use softDeletes;
    use Blameable;

    protected $connection = 'tenant';
    protected $table = 'change_meters';


    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'construct_company',
        'reading_seal_no',
        'terminal_seal_no',
        'meter_box_seal_no',
        'meter_start_no',
        'previous_meter_no',
        'used_by',
    ];


    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'meter_issue_id' => 'integer',
        'construct_company' => 'string',
        'reading_seal_no' => 'string',
        'terminal_seal_no' => 'string',
        'meter_box_seal_no' => 'string',
        'meter_start_no' => 'integer',
        'previous_meter_no' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Define the relationship with the MasterSetup model for reading_area.
     */
    public function readingArea()
    {
        return $this->belongsTo(MasterSetup::class, 'reading_area', 'id');
    }

    /**
     * Define the relationship with the MasterSetup model for transformer.
     */
    public function transformer()
    {
        return $this->belongsTo(MasterSetup::class, 'transformer', 'id');
    }

    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }

    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }

    public function meterIssueNumber()
    {
        return $this->belongsTo(MeterIssue::class, 'previous_meter_no', 'meter_no');
    }
}
