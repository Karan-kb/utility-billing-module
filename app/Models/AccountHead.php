<?php

namespace App\Models;


use App\Models\AccountGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\softDeletes;

class AccountHead extends Model
{
    protected $connection = 'tenant';

    use softDeletes, HasFactory;

    protected $table = 'account_heads';

    protected static $useFiscalYear = false;


    protected $fillable = [
        'name',
        'name_np',
        'type',
        'amount',
        'account_group_id',
        'is_active',
        'deleted_at'
    ];

    protected $casts = [
        'name' => 'string',
        'name_np' => 'string',
        'account_group_id' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    protected $dates = ['deleted_at'];


    public function accountGroup()
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }
}
