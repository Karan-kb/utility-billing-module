<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemProcessLog extends Model
{
    use HasFactory;
    protected $connection = 'tenant';

    // Table name (optional if following Laravel convention)
    protected $table = 'system_process_logs';

    // Mass assignable fields
    protected $fillable = [
        'member_entry_id',
        'meter_reading_entry_id',
        'receipt_id',
        'service',
        'stage',
        'action',
        'data',
        'level',
    ];

    // Cast `data` JSON column to array automatically
    protected $casts = [
        'data' => 'array',
    ];

    
}