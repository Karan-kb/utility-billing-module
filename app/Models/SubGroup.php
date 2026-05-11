<?php

namespace App\Models;


use App\Models\MainGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SubGroup extends Model
{
    protected $connection = 'tenant';

    protected static $useFiscalYear = false;

    use softDeletes, HasFactory;



    protected $casts = [
        'name' => 'string',
        'main_group_id' => 'integer',
        'deleted_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'name',
        'main_group_id',
        'code',
        'ranking_for_trial',
        'is_active',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];



    public function mainGroup()
    {
        return $this->belongsTo(MainGroup::class, 'main_group_id');

    }
}
