<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;
    protected $connection = 'mysql';
    protected $table = 'tenants';

    protected $fillable = [
        'id',
        'database',
        'company_id',
        'data',
         'software_type', 
    ];

    protected $casts = [
        'data' => 'array',
        'software_type' => 'integer',
    ];

    public $incrementing = false;
    protected $keyType = 'string';


  public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id', 'id');
    }



public static function softwareTypeByCompanyId(int $companyId): int
{
    return self::where('company_id', $companyId)
        ->value('software_type') ?? 0; // default to 0 (Bidut)
}
}
