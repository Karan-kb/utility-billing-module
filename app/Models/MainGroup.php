<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class MainGroup extends Model
{
    protected $connection = 'tenant';

    use SoftDeletes, HasFactory;
    protected $table = 'main_groups';


    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $dates = ['deleted_at'];



    public function subGroups(): HasMany
    {
        return $this->hasMany(SubGroup::class, 'main_group_id');
    }
}
