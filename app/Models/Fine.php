<?php

namespace App\Models;

use App\Observers\activity_logs\FineObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fine extends BaseModel
{
    use HasFactory, SoftDeletes;
    protected $connection = 'tenant';

    protected $table = 'fines';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'uuid',
        'meter_issue_id',
        'meter_reading_entry_id',
        'fine_type',
        'applied_fine_percentage',
        'fine_reapplied',
        'amount',
        'status',
        'voucher_no',
        'created_at'
        // 'fiscal_year_id',
    ];

    protected $casts = [
        'date_in_ad' => 'date',
        'fine_type' => 'integer',
        'status' => 'integer',
        'amount' => 'decimal:2',
    ];



    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id');
    }

    public function meterReadingEntry()
    {
        return $this->belongsTo(MeterReadingEntry::class, 'meter_reading_entry_id');
    }
    // protected static function booted()
    // {
    //     static::observe(FineObserver::class);
    // }
}
