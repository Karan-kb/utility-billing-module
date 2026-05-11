<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseAndReceivableTracker extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;

    protected $table = 'expense_and_receivable_trackers';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'fiscal_year_id',
        'voucher_no',
        'type',
        'date_in_bs',
    ];


    public function items()
{
    return $this->hasMany(ExpenseAndReceivableItem::class, 'tracker_id');
}
}