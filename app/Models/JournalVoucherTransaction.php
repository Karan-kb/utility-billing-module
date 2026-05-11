<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TenantModel;

class JournalVoucherTransaction extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $fillable = [
        'journal_voucher_id',
        'main_group_id',
        'account_group_id',
        'account_head_id',
        'sub_group_id',
        'account_code',
        'particulars',
        'type',
        'debit',
        'credit'
    ];


    public function journalVoucher()
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    public function mainGroup()
    {
        return $this->belongsTo(MainGroup::class, 'main_group_id');
    }

    public function accountGroup()
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }

    public function subGroup()
    {
        return $this->belongsTo(SubGroup::class, 'sub_group_id');
    }
}
