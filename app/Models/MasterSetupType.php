<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class MasterSetupType extends Model
{
    protected $connection = 'tenant';

    use softDeletes, HasFactory;

    protected $fillable = [
        'name',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];

}
