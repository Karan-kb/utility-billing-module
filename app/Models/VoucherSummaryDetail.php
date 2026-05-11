<?php

namespace App\Models;

use App\Models\Scopes\CompanyIdScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherSummaryDetail extends Model
{
    protected $connection = 'tenant';
    protected $table = 'voucher_summary_details';
    protected static $useFiscalYear = false;

    use SoftDeletes;


    protected $fillable = [
        'voucher_summary_id',
        'is_cancelled',
        'particulars',              
        'debit',
        'credit',
        'account_head_id',
        'member_entry_id',
    ];
    protected $casts = [
        'voucher_summary_id' => 'integer',
        'particulars' => 'string',
        'account_head_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];



    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');

    }

    public function voucherSummary()
    {
        return $this->belongsTo(VoucherSummary::class, 'voucher_summary_id');

    }


    public function account_head()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }

     public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id');
    }

  
}
