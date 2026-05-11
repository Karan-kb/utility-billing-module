<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningMeterDepositEntry extends BaseModel
{
    protected $connection = 'tenant';

    use HasFactory, SoftDeletes;


    protected $table = 'opening_meter_deposit_entries';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'meter_issue_id',
        'amount',
    ];
    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'meter_issue_id' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }

    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id');
    }



}
