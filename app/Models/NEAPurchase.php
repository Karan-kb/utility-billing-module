<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class NEAPurchase extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'nea_purchases';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'month',
        'transformer_id',
        'fiscal_year_id',
        'total_units',
        'amount',
    ];

    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'month' => 'integer',
        'transformer_id' => 'integer',
        'total_units' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Define the relationship with the OpeningMeterDepositEntry model.
     */
    public function transformer()
    {
        return $this->belongsTo(MasterSetup::class, 'transformer_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(NEAPaymentEntry::class, 'transformer_id', 'transformer_id');
    }
}
