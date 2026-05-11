<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;

class DisabilityDiscount extends BaseModel
{
    
    protected $connection = 'tenant';

    protected $table = 'disable_discounts';

    protected static $useFiscalYear = false;


    protected $fillable = [
        'units',
        'is_applied',
    ];

    protected $casts = [
        'units' => 'integer',
        'is_applied' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
