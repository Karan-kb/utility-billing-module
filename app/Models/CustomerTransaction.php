<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerTransaction extends Model
{
    use HasFactory, SoftDeletes;
    protected $connection = 'tenant';
    protected $table = 'customer_transactions';

    protected $fillable = [
        'member_entry_id',
        'transaction_date',
        'due',
        'transaction_type',
        'charge_type',
        'amount',
        'direction',
        'reference_id',
        'receipt_id',
        'created_at'
    ];

    public const TRANSACTION_TYPES = [
        1 => 'Meter Read',
        2 => 'Mahasul Receipt',
        3 => 'Meter',
        4 => 'Share',
        5 => 'Opening Mahasul',
    ];
public function memberEntry()
{
    return $this->belongsTo(MemberEntry::class, 'member_entry_id');
}
}
