<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

class CheckMenuPermission
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $path = '/' . trim(str_replace('api/', '', $request->path()), '/');
        $permissions = config('menu_permissions');

        // Super admin + master admin bypass
        if ($user->hasRole('super admin') || $user->hasRole('master_admin')) {
            return $next($request);
        }

        if (!$user->company_id) {
            return response()->json(['message' => 'No company assigned'], 403);
        }

        $menuId = null;
        foreach ($permissions as $pattern => $id) {
            $pattern = rtrim($pattern, '/') . '(/.*)?$';
            if (preg_match("#^$pattern#", $path)) {
                $menuId = $id;
                break;
            }
        }


        if (!$menuId) {
            return response()->json(['message' => 'Menu not configured for this route'], 403);
        }

        // Determine permission type from HTTP method
        $method = $request->method();
        $permissionType = match ($method) {
            'GET' => 'has_view_access',
            'POST' => str_contains($path, 'edit') ? 'has_update_access' : 'has_create_access',
            'PUT', 'PATCH' => 'has_update_access',
            'DELETE' => 'has_delete_access',
            default => null
        };

        if (!$permissionType) {
            return response()->json(['message' => 'Cannot determine permission type'], 403);
        }

        // Check permission
        if (!$user->hasMenuPermission($menuId, $permissionType)) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        return $next($request);
    }
}