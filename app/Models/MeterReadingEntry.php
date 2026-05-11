<?php

namespace App\Models;

use App\Observers\MeterReadingEntryObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Blameable;

class MeterReadingEntry extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes, Blameable;


    protected $table = 'meter_reading_entries';
    protected $fillable = [
        'reader_id',
        'uuid',
        'is_synced',
        'fiscal_year_id',
        'entry_type',
        'status',
        'reading_month_in_bs',
        'reading_date_in_bs',
        'reading_date_in_ad',
        'meter_issue_id',
        'unit_before_discount',
        'discount_unit_for_disable',
        'tariff_setup_id',
        'previous_unit',
        'current_unit',
        'total_unit',
        'unit_amount',
        'minimum_demand',
        'subsidy_charge',
        'service_charge',
        'other_charge',
        'fine_amount',
        'applied_fine_percentage',
        'total_charge',
        'sub_total_charge'
    ];
    protected $casts = [
        'reading_month_in_bs' => 'integer',
        'entry_type' => 'integer',
        'reading_date_in_bs' => 'string',
        'reading_date_in_ad' => 'date:Y-m-d',
        'meter_issue_id' => 'integer',
        'unit_before_discount' => 'integer',
        'discount_unit_for_disable' => 'integer',
        'tariff_setup_id' => 'integer',
        'previous_unit' => 'integer',
        'current_unit' => 'integer',
        'total_unit' => 'integer',
        'unit_amount' => 'decimal:2',
        'minimum_demand' => 'decimal:2',
        'subsidy_charge' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'other_charge' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'applied_fine_percentage' => 'decimal:2',
        'total_charge' => 'decimal:2',
        'sub_total_charge' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function tariffSetup()
    {
        return $this->belongsTo(TariffSetup::class, 'tariff_setup_id');
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
    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }

    public function fines()
    {
        return $this->hasMany(
            Fine::class,
            'meter_reading_entry_id',
            'id'
        );
    }

    public function customerTransaction()
    {
        return $this->hasMany(CustomerTransaction::class, 'reference_id', 'id');
    }









    public function mahsulReceiptAlternative()
    {
        return $this->hasOne(
            MahasulReceiptEntry::class,
            'meter_reading_entry_id',
            'id'
        )
            ->whereNull('mahasul_receipts.deleted_at')
            ->where('is_cancel', 0);
    }




    public function reader()
    {
        return $this->belongsTo(User::class, 'reader_id');
    }

    public function meterIssueNumber()
    {
        return $this->belongsTo(MeterIssue::class, 'meter_issue_id', 'id');
    }


    public function memberOpening()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'id');
    }
    public function meterIssueOpening()
    {
        return $this->belongsTo(\App\Models\MeterIssue::class, 'meter_issue_id', 'id');
    }



}
