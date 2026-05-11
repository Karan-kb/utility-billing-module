<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinePost extends BaseModel
{
    protected $connection = 'tenant';

    use SoftDeletes;


    protected $table = 'fine_posts';

    protected $fillable = [
        'member_entry_id',
        'fine',
        'details',
    ];

    public function memberEntry()
    {
        return $this->belongsTo(MemberEntry::class, 'member_entry_id', 'member_entry_id');
    }
}
