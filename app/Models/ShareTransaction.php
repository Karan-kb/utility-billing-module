<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareTransaction extends BaseModel
{
    use SoftDeletes;

    protected $table = 'share_transactions';
    protected $connection = 'tenant';

    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'member_entry_id',
        'share_type',
        'share_certificate_no',
        'share_quantity',
        'return_share_quantity',
        'share_value',
        'voucher_no',
        'transaction_type',
        'service_charge',
        'amount',
        'return_amount',
        'is_cancel',
    ];

    protected $casts = [
        'date_in_ad' => 'date:Y-m-d',
        'member_entry_id' => 'integer',
        'share_quantity' => 'integer',
        'return_share_quantity' => 'integer',
        'share_value' => 'integer',
        'transaction_type' => 'integer',
        'service_charge' => 'decimal:2',
        'amount' => 'decimal:2',
        'return_amount' => 'decimal:2',
    ];

    /**
     * Relationship: A share transaction belongs to a member
     */
    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id');
    }
    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id');
    }
}
