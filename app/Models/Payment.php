<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends BaseModel
{
    use SoftDeletes;

    protected $table = 'payments';
    protected $connection = 'tenant';

    protected static $useFiscalYear = false;

    protected $fillable = [
        'reference_id',
        'type',
        'amount',
        'payment_mode',
        'cheque_no',
        'bank_id',
        'collector_id',
    ];

    protected $casts = [
        'reference_id' => 'integer',
        'type' => 'integer',
        'amount' => 'decimal:2',
        'payment_mode' => 'integer',
        'bank_id' => 'integer',
    ];



    /**
     * Relationship: bank (comes from master_setups)
     */
    // public function bank()
    // {
    //     return $this->belongsTo(MasterSetup::class, 'bank_id');
    // }
    public function bank()
{
    return $this->belongsTo(AccountHead::class, 'bank_id')
                ->where('account_group_id', 10) // bank accounts
                ->where('is_active', 1)
                ->whereNull('deleted_at');
}


}
