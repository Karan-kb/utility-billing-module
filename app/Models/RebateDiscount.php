<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RebateDiscount extends BaseModel
{
    use SoftDeletes;
    protected $connection = 'tenant';

    protected $table = 'rebate_discounts';

    protected static $useFiscalYear = false;

    protected $fillable = [
        'name',
        'type',
        'value',
        'min_units',
        'max_units',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'value'  => 'decimal:2',
    ];
}
