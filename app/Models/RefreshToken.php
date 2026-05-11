<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RefreshToken extends Model
{
    use HasFactory;
    protected $connection = 'mysql';


    // Mass assignable fields
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    // Treat expires_at as a Carbon date
    protected $dates = ['expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Define relationship to User
     * Works even if user_id is nullable
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Scope to get only valid (non-expired) tokens
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
