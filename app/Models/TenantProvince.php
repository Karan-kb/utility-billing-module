<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantProvince extends Model
{
    protected $connection = 'tenant';

    protected $table = 'provinces';

    protected $fillable = [
        'id',
        'name_en',
        'name_np',
    ];
    public $timestamps = true; // if your table has created_at and updated_at


    // helper to get model instance with a dynamic connection


    public function districts()
    {
        return $this->hasMany(TenantDistrict::class, 'province_id', 'id');
    }
}
