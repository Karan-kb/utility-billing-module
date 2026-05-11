<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenuPermission;
use App\Models\User;
use Illuminate\Http\Request;

class RoleMenuPermissionController extends Controller
{

    public function getMenusList()
    {
        try {
            $menus = Menu::with('children')->where('parent_id', 0)->orderBy('menu_order')->get();

            $formattedMenus = $menus->map(function ($menu) {
                return $this->formatMenu($menu);
            });

            return response()->json([
                'status' => 'Menus fetched successfully.',
                'menus' => $formattedMenus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch menus: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatMenu($menu)
    {
        return [
            'id' => $menu->id,
            'menu_name' => $menu->menu_name,
            'children' => $menu->children->map(function ($child) {
                return $this->formatMenu($child);
            })
        ];
    }


    public function getRoleMenus(Request $request, $roleId)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::find($roleId);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $menus = Menu::with([
            'rolePermissions' => function ($q) use ($roleId) {
                $q->where('role_id', $roleId);
            }
        ])
            ->orderBy('menu_order')
            ->get();

        $data = $menus->map(function ($menu) {
            $permission = $menu->rolePermissions->first();

            return [
                'id' => $menu->id,
                'menu_name' => $menu->menu_name,
                'parent_id' => $menu->parent_id,
                'menu_order' => $menu->menu_order,
                'has_view_access' => $permission?->has_view_access,
                'has_create_access' => $permission?->has_create_access,
                'has_update_access' => $permission?->has_update_access,
                'has_delete_access' => $permission?->has_delete_access,
            ];
        });


        return response()->json([
            'data' => $data,
        ]);
    }

    public function assignMenusToRole(Request $request, $roleId)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = Role::find($roleId);

        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.menu_id' => 'required|exists:menus,id',
            'permissions.*.has_view_access' => 'required|boolean',
            'permissions.*.has_create_access' => 'required|boolean',
            'permissions.*.has_update_access' => 'required|boolean',
            'permissions.*.has_delete_access' => 'required|boolean',
        ]);

        foreach ($request->permissions as $perm) {
            RoleMenuPermission::updateOrCreate(
                [
                    'role_id' => $roleId,
                    'menu_id' => $perm['menu_id'],
                ],
                [
                    'has_view_access' => $perm['has_view_access'],
                    'has_create_access' => $perm['has_create_access'],
                    'has_update_access' => $perm['has_update_access'],
                    'has_delete_access' => $perm['has_delete_access'],
                ]
            );
        }

        return response()->json([
            'message' => 'Role menu permissions assigned and updated successfully'
        ]);
    }

    // public function getUserMenus(Request $request)
    // {
    //     $user = $request->user();
    //     $roles = $user->roles->pluck('id');

    //     $menus = Menu::with('children')->where('parent_id', 0)->orderBy('menu_order')->get();

    //     $filteredMenus = [];

    //     foreach ($menus as $menu) {
    //         $menuData = $this->filterMenu($menu, $roles);
    //         if ($menuData) {
    //             $filteredMenus[] = $menuData;
    //         }
    //     }

    //     return response()->json($filteredMenus);
    // }
    public function getUserMenus(Request $request)
{
    $user = $request->user();

    if ($user->hasRole('master_admin')) {

        $menus = Menu::with('children')
            ->where('parent_id', 0)
            ->orderBy('menu_order')
            ->get();

        return response()->json($menus);
    }

    // Normal users
    $roles = $user->roles->pluck('id');

    $menus = Menu::with('children')
        ->where('parent_id', 0)
        ->orderBy('menu_order')
        ->get();

    $filteredMenus = [];

    foreach ($menus as $menu) {
        $menuData = $this->filterMenu($menu, $roles);

        if ($menuData) {
            $filteredMenus[] = $menuData;
        }
    }

    return response()->json($filteredMenus);
}

    private function filterMenu($menu, $roles)
    {
        $hasAccess = RoleMenuPermission::whereIn('role_id', $roles)
            ->where('menu_id', $menu->id)
            ->where(function ($q) {
                $q->where('has_view_access', 1)
                    ->orWhere('has_create_access', 1)
                    ->orWhere('has_update_access', 1)
                    ->orWhere('has_delete_access', 1);
            })->exists();

        $children = [];
        foreach ($menu->children as $child) {
            $childData = $this->filterMenu($child, $roles);
            if ($childData) {
                $children[] = $childData;
            }
        }

        if ($hasAccess || count($children) > 0) {
            return [
                'id' => $menu->id,
                'menu_name' => $menu->menu_name,
                'children' => $children
            ];
        }
        return null;
    }


}
