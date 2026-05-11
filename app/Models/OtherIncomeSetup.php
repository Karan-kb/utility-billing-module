<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherIncomeSetup extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'other_income_setups';

    protected $fillable = [
        'income_head_en',
        'income_head_np',
        'charge_amount',
        'account_head_id',
    ];

    protected $casts = [
        'income_head_en' => 'string',
        'income_head_np' => 'string',
        'charge_amount' => 'decimal:2',
        'account_head_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }
}
