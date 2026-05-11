<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareOpeningEntry extends BaseModel
{
    protected $connection = 'tenant';

    use HasFactory, SoftDeletes;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'opening_share_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'date_in_bs',
        'date_in_ad',
        'member_entry_id',
        'share_type',
        'share_certificate_no',
        'share_quantity',
        'share_value',
        'amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_in_bs' => 'string',
        'date_in_ad' => 'date',
        'member_entry_id' => 'integer',
        'share_type' => 'string',
        'share_certificate_no' => 'string',
        'share_quantity' => 'decimal:2',
        'share_value' => 'decimal:2',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the member entry associated with this share opening entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'member_entry_id');
    }

    /**
     * Get the meter issue associated with this share opening entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function meterIssue()
    {
        return $this->belongsTo(MeterIssue::class, 'share_certificate_no', 'meter_no');
    }
}
