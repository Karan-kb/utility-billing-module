<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantDatabase
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Tenant not found'], 403);
        }

        $company = \App\Models\Company::where('user_id', $user->id)->first();

        if (!$company) {
            return response()->json(['message' => 'Tenant not found'], 403);
        }

        setTenantConnection($company);

        return $next($request);
    }
}
