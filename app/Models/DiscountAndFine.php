<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountAndFine extends BaseModel
{
    protected $connection = 'tenant';
    use SoftDeletes;

    protected $table = 'discount_and_fines';

    protected static $useFiscalYear = false;

    protected $fillable = [
        'type',
        'amount_type',
        'amount',
        'description',
        'days_after',
        'is_active',
    ];

    protected $casts = [
        'type' => 'integer',          // 1=discount, 2=fine, 3=rebate
        'amount_type' => 'integer',   // 1=percent, 2=amount
        'amount' => 'decimal:2',
        'days_after' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
