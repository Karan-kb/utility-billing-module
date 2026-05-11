<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;

class BlacklistPeriod extends BaseModel
{
    protected $connection = 'tenant';

    protected $table = 'blacklist_periods';

    protected static $useFiscalYear = false;


    protected $fillable = [
        'days',
        'amount',
        'is_applied',
    ];

    protected $casts = [
        'days' => 'integer',
        'amount' => 'decimal:2',
        'is_applied' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
