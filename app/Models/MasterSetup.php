<?php

namespace App\Models;
use App\Models\BaseTenantModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterSetup extends Model
{
    protected $connection = 'tenant';

    protected static $useFiscalYear = false;

    use SoftDeletes;

    protected $table = 'master_setups';


    protected $fillable = [
        'master_setup_type_id',
        'name_en',
        'name_np',
        'order_no',
        'is_active',
    ];
    protected $casts = [
        'master_setup_type_id' => 'integer',
        'name_en' => 'string',
        'name_np' => 'string',
        'order_no' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];
    public function masterSetup()
    {
        return $this->belongsTo(MasterSetup::class, 'id', 'id');
    }
}
