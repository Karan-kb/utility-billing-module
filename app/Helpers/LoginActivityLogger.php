<?php

namespace App\Helpers;

use App\Models\LoginActivityLog;
use Illuminate\Support\Facades\Request;

class LoginActivityLogger
{
    /**
     * Log a user activity
     *
     * @param int|null $userId
     * @param string $action
     * @return void
     */
    public static function log(?int $userId, string $action): void
    {
        LoginActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }
}