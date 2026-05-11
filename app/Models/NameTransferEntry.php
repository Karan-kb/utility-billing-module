<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NameTransferEntry extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;

    protected $table = 'name_transfer_entries';

    /**
     * Corrected fillable (removed trailing spaces)
     */
    protected $fillable = [
        'previous_member_entry_id',
        'new_member_entry_id',
    ];

    protected $casts = [
        'previous_member_entry_id' => 'integer',
        'new_member_entry_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function previousMember()
    {
        return $this->belongsTo(MemberEntry::class, 'previous_member_entry_id', 'id')->withTrashed();
    }


    public function newMember()
    {
        return $this->belongsTo(MemberEntry::class, 'new_member_entry_id', 'id');
    }


    public function meterIssue()
    {
        return $this->hasOne(MeterIssue::class, 'member_entry_id', 'previous_member_entry_id');
    }
    public function meterIssueOfNewMember()
    {
        return $this->hasOne(MeterIssue::class, 'member_entry_id', 'new_member_entry_id')
            ->where('is_active', 1);
    }

public function previousMeterIssue()
{
    return $this->belongsTo(MeterIssue::class, 'previous_meter_issue_id');
}

}
