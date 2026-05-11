<?php

namespace App\Models;

use App\Models\MainGroup;
use App\Models\SubGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGroup extends Model
{
    protected $connection = 'tenant';

    use softDeletes, HasFactory;

    protected static $useFiscalYear = false;

    protected $table = 'account_groups';


    protected $fillable = [
        'name',
        'sub_group_id',
        'is_active',
        'deleted_at'
    ];


    protected $casts = [
        'name' => 'string',
        'sub_group_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function mainGroup()
    {
        return $this->belongsTO(MainGroup::class, 'main_group_id');
    }

    public function subGroup()
    {
        return $this->belongsTO(SubGroup::class, 'sub_group_id');
    }

    protected $dates = ['deleted_at'];


}
