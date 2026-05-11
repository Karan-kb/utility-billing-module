<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalVoucherDetail extends Model
{
    protected $connection = 'tenant';

        use SoftDeletes;

    protected $table = 'journal_voucher_details';

    protected $fillable = [
        'journal_voucher_id',
        'account_head_id',
        'cheque_no',
        'particulars',
        'debit',
        'credit',
    ];

    /**
     * Relationships
     */

    // Belongs to Journal Voucher
    public function journalVoucher()
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    // Account Head relation (if exists)
    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }
}