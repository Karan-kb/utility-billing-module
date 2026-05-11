<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Blameable;

class FiscalYear extends Model
{
    use Blameable;

    // Use the tenant database connection
    protected $connection = 'tenant';

    // Table name
    protected $table = 'fiscal_years';

    protected static $useFiscalYear = false;



    // Fillable columns
    protected $fillable = [
        'year_en',
        'year_np',
        'start_date',
        'end_date',
        'status',
    ];

    // Casts
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'boolean',
    ];
}
