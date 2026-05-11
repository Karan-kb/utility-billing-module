<?php

namespace App\Models;

use App\Observers\NonMemberPaymentObserver;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class NonMemberPayment extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'non_member_payments';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'customer_name_en',
        'customer_name_np',
        'address',
        'mobile_no',
        'demand_charge',
        'consumption_unit',
        'amount',
        'voucher_no',
        'is_cancel',
    ];

    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'customer_name_en' => 'string',
        'customer_name_np' => 'string',
        'address' => 'string',
        'mobile_no' => 'string',
        'demand_charge' => 'decimal:2',
        'consumption_unit' => 'integer',
        'amount' => 'decimal:2',
        'voucher_no' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id')
                    ->where('type', 9); // type 9 = non member payment
    }


}
