<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\Auth;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken || !str_contains($bearerToken, '|')) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        [$id, $plainText] = explode('|', $bearerToken, 2);
        $hashedToken = hash('sha256', $plainText);

        $tokenRecord = PersonalAccessToken::where('id', $id)
            ->where('token', $hashedToken)
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Invalid access token'], 401);
        }

        if ($tokenRecord->expires_at && now()->greaterThan($tokenRecord->expires_at)) {
            $refreshToken = RefreshToken::where('user_id', $tokenRecord->tokenable_id)
                ->where('expires_at', '>', now())
                ->first();

            return response()->json([
                'message' => $refreshToken
                    ? 'Access token expired, please refresh your token'
                    : 'Access token expired and refresh token not found. Please login again'
            ], 401);
        }

        // Set user in the request
        $request->setUserResolver(fn() => $tokenRecord->tokenable);
        Auth::login($tokenRecord->tokenable);

        return $next($request);
    }
}
