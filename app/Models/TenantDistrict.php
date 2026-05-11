<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDistrict extends Model
{
    protected $connection = 'tenant';

    protected $table = 'districts';

    protected $fillable = [
        'province_id',
        'name_en',
        'name_np',
    ];
    public $timestamps = true; // if your table has created_at and updated_at

    public function province()
    {
        return $this->belongsTo(TenantProvince::class, 'province_id', 'id');
    }

    public function municipalities()
    {
        return $this->hasMany(TenantMunicipality::class, 'district_id', 'id');
    }
}
