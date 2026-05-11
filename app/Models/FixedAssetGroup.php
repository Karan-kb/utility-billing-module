<?php

namespace App\Models;

use App\Models\Scopes\CompanyIdScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAssetGroup extends Model
{
    protected $connection = 'tenant';

    use SoftDeletes, HasFactory;


    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'depreciation_percent',
        'code',
        'account_group_id',
        'is_active',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];

    public function accountGroup()
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }
}
