<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $currentToken = $request->bearerToken();
        $token = PersonalAccessToken::findToken($currentToken);

        // Check if this is an impersonation token
        $isImpersonating = $token && in_array('impersonate', $token->abilities);
         $isMasterAdmin   = $token && in_array('master_admin', $token->abilities);

        if ($isImpersonating|| $isMasterAdmin) {
            // Extract company ID from token abilities
            $companyId = null;
            foreach ($token->abilities as $ability) {
                if (strpos($ability, 'company:') === 0) {
                    $companyId = substr($ability, 8); // Remove 'company:' prefix
                    break;
                }
            }

            if (!$companyId) {
                return response()->json(['error' => 'No company specified in impersonation token'], 400);
            }

            $tenant = Tenant::where('company_id', $companyId)->first();

            if (!$tenant) {
                return response()->json(['error' => 'Tenant not found for company'], 404);
            }
        } else {
            // Normal user flow
            $company = $user->company;

            if (!$company) {
                return response()->json(['error' => 'Company not found for this user'], 404);
            }

            $tenant = Tenant::where('company_id', $company->id)->first();

            if (!$tenant) {
                return response()->json(['error' => 'Tenant not found'], 404);
            }
        }

        $databaseName = $tenant->database;
        if (empty($databaseName) && !empty($tenant->data)) {
            $data = is_array($tenant->data) ? $tenant->data : json_decode($tenant->data, true);
            $databaseName = $data['database'] ?? null;
        }

        if (empty($databaseName)) {
            Log::error("Tenant database not defined", [
                'tenant_id' => $tenant->id,
                'company_id' => $isImpersonating ? $companyId : $company->id,
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Tenant database not defined'], 500);
        }

        config(['database.connections.tenant.database' => $databaseName]);

        try {
            DB::purge('tenant');
            DB::connection('tenant')->reconnect();
        } catch (\Exception $e) {
            Log::error("Failed to connect tenant database", [
                'tenant_id' => $tenant->id,
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to connect tenant database'], 500);
        }

        // Log::debug("Tenant database switched successfully", [
        //     'tenant_id' => $tenant->id,
        //     'database' => $databaseName,
        //     'user_id' => $user->id
        // ]);

        return $next($request);
    }


}
