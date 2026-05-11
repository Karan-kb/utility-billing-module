<?php

namespace App\Models;

use App\Observers\NEAPaymentEntryObserver;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class NEAPaymentEntry extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'nea_payments';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'month',
        'transformer_id',
        'due_amount',
        'paid_amount',
        'fine_amount',
        'rebate_amount',
        'total_amount',
        'voucher_no',
        'is_cancel',
    ];
    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'month' => 'integer',
        'transformer_id' => 'integer',
        'due_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'rebate_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'voucher_no' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    public function purchase()
    {
        return $this->belongsTo(NEAPurchase::class, 'purchase_id', 'id');
    }

    // public function transformer()
    // {
    //     return $this->belongsTo(MasterSetup::class, 'transformer_id', 'id');
    // }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id')
            ->where('type', 10);
    }
    
    public function transformer()
    {
        return $this->belongsTo(MasterSetup::class, 'transformer_id');
    }
    
    public function member()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'member_entry_id');
    }

   


}
