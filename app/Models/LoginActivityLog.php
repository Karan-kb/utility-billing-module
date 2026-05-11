<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginActivityLog extends Model
{
    use HasFactory;
    protected $connection = 'mysql'; 

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
    ];
}