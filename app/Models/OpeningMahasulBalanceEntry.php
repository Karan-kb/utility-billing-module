<?php

namespace App\Models;

use App\Observers\OpeningMahasulBalanceEntryObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningMahasulBalanceEntry extends BaseModel
{
    protected $connection = 'tenant';

    use HasFactory, SoftDeletes;


    protected $table = 'opening_mahasul_entries';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'member_entry_id',
        'mahasul_amount',
        'demand_charge',
        'subsidy_charge',
        'service_charge',
        'other_charge',
        'fine_amount',
        'total_amount',
    ];

    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'member_entry_id' => 'integer',
        'mahasul_amount' => 'decimal:2',
        'demand_charge' => 'decimal:2',
        'subsidy_charge' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'other_charge' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }
    public function meterIssue()
    {
        return $this->belongsTo(\App\Models\MeterIssue::class, 'meter_issue_id', 'id');
    }
    public function meterIssueNumber()
    {
        return $this->belongsTo(MeterIssue::class, 'member_entry_id', 'member_entry_id');
    }

    // protected static function booted()
    // {
    //     static::observe(OpeningMahasulBalanceEntryObserver::class);
    // }

}
