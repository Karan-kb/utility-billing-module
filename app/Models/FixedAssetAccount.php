<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAssetAccount extends Model
{
    protected $connection = 'tenant';

    use SoftDeletes, HasFactory;


    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'code',
        'fixed_asset_group_id',
        'is_active',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];

    public function fixedAssetGroup()
    {
        return $this->belongsTo(FixedAssetGroup::class, 'fixed_asset_group_id');
    }

}
