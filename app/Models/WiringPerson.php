<?php

namespace App\Models;

use Dba\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WiringPerson extends BaseModel
{
    protected $connection = 'tenant';
    
    use SoftDeletes;

    protected $table = 'wiring_persons';

    protected static $useFiscalYear = false;

    protected $fillable = [
        'wiring_person_name',
        'wiring_person_no',
    ];

    protected $casts = [
        'wiring_person_name' => 'string',
        'wiring_person_no' => 'string',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function memberEntries()
    {
        return $this->hasMany(MemberEntry::class, 'wiring_person_id', 'id');
    }
}
