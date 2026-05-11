<?php

namespace App\Models;

use App\Models\Scopes\CompanyIdScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherSummary extends Model
{
    protected $connection = 'tenant';

    use SoftDeletes;

    protected $table = 'voucher_summaries';
    protected $fillable = [
        'date',
        'voucher_no',
        'particulars',       
        'reference_type',
        'reference_id',
        'fiscal_year_id',
        'status',
        'reason',

    ];


    protected $casts = [
        'date' => 'date',
        'created_by' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    protected $dates = ['deleted_at'];



    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');

    }

    public function voucherSummaryDetail()
    {
        return $this->hasMany(VoucherSummaryDetail::class, 'voucher_summary_id');
    }



}
