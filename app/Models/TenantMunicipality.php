<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantMunicipality extends Model
{
    protected $connection = 'tenant';

    protected $table = 'municipalities';

    protected $fillable = [
        'district_id',
        'name_en',
        'name_np',
    ];

    public $timestamps = true; // if your table has created_at and updated_at


    public function district()
    {
        return $this->belongsTo(TenantDistrict::class, 'district_id', 'id');
    }
}
