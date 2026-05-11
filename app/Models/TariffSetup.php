<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TariffSetup extends Model
{
    protected $connection = 'tenant';

    protected static $useFiscalYear = false;

    use SoftDeletes;
    protected $table = 'tariff_setups';


    protected $fillable = ['rule_name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];
}
