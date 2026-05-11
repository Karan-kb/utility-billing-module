<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalVoucher extends BaseModel
{
    protected $connection = 'tenant';

        use SoftDeletes;
    protected $table = 'journal_vouchers';

    protected $fillable = [
        'fiscal_year_id',
        'date_in_bs',
        'voucher_no',
        
    ];

    /**
     * Relationships
     */

    // One voucher has many details
    public function details()
    {
        return $this->hasMany(JournalVoucherDetail::class, 'journal_voucher_id');
    }

  
}