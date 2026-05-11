<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseAndReceivableItem extends Model
{    
    protected $connection = 'tenant';

    use SoftDeletes;

    protected $table = 'expense_and_receivable_items';
    protected $fillable = [
        'tracker_id',
        'account_head_id',
        'ref_bill_no',
        'bank_id',
        'amount',
        'particular'
    ];

     public function tracker()
    {
        return $this->belongsTo(ExpenseAndReceivableTracker::class, 'tracker_id');
    }

    // Relationship to account head
    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }
}