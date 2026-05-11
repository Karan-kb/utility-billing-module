<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->setupDefaultAdmin();
    }

    private function setupDefaultAdmin()
    {
        if (!Role::where('name', 'admin')->exists()) {
            $adminRole = Role::create([
                'name' => 'admin',
                'guard_name' => 'api'
            ]);

            $permissions = [
                'view roles',
                'create roles',
                'edit roles',
                'delete roles',
                'view permissions',
                'create permissions',
                'edit permissions',
                'delete permissions',
                'view users',
                'edit users',
                'delete users'
            ];

            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'api'
                ]);
                $adminRole->givePermissionTo($permission);
            }

            if (!User::where('email', 'admin@gmail.com')->exists()) {
                $adminUser = User::create([
                    'name' => 'Admin User',
                    'email' => 'admin@gmail.com',
                    'password' => Hash::make('12345678'),
                ]);

                $adminUser->assignRole('admin');
            }
        }
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $userRole = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => 'api'
        ]);
        $user->assignRole('user');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

  



    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user->name,
            'message' => 'Successfully logged in',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function createRole(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('create roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'api'
        ]);

        return response()->json(['message' => "Role {$role->name} created successfully"], 201);
    }

    public function listRoles(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roles = Role::all()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ];
        });

        return response()->json(['roles' => $roles]);
    }

    public function editRole(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('edit roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|exists:roles,id',
            'name' => 'required|string|max:255|unique:roles,name,' . $request->id,
        ]);

        $role = Role::findOrFail($request->id);
        if ($role->name === 'admin' || $role->name === 'user') {
            return response()->json(['message' => 'Cannot edit default roles'], 403);
        }
        $role->update(['name' => $request->name]);

        return response()->json(['message' => "Role updated to {$role->name} successfully"]);
    }

    public function deleteRole(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('delete roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($request->id);
        if ($role->name === 'admin' || $role->name === 'user') {
            return response()->json(['message' => 'Cannot delete default roles'], 403);
        }
        $role->delete();

        return response()->json(['message' => "Role {$role->name} deleted successfully"]);
    }

    public function createPermission(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('create permissions')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'api'
        ]);

        return response()->json(['message' => "Permission {$permission->name} created successfully"], 201);
    }

    public function listPermissions(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view permissions')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all()->pluck('name');

        return response()->json(['permissions' => $permissions]);
    }

    public function editPermission(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('edit permissions')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|exists:permissions,id',
            'name' => 'required|string|max:255|unique:permissions,name,' . $request->id,
        ]);

        $permission = Permission::findOrFail($request->id);
        $permission->update(['name' => $request->name]);

        return response()->json(['message' => "Permission updated to {$permission->name} successfully"]);
    }

    public function deletePermission(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('delete permissions')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|exists:permissions,id',
        ]);

        $permission = Permission::findOrFail($request->id);
        $permission->delete();

        return response()->json(['message' => "Permission {$permission->name} deleted successfully"]);
    }

    public function assignRole(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('edit users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::where('email', $request->email)->first();
        $user->syncRoles([$request->role]);

        return response()->json(['message' => "Role {$request->role} assigned to user {$user->name}"]);
    }

    public function assignPermissionToRole(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('edit roles')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::findByName($request->role, 'api');
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => "Permissions assigned to role {$role->name}"]);
    }

    public function listUsers(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::all()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ];
        });

        return response()->json(['users' => $users]);
    }

    public function editUser(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('edit users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $request->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($request->id);
        if ($user->email === 'admin@gmail.com') {
            return response()->json(['message' => 'Cannot edit default admin user'], 403);
        }
        $updateData = [];
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json(['message' => "User {$user->name} updated successfully"]);
    }

    public function deleteUser(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('delete users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->id);
        if ($user->email === 'admin@gmail.com') {
            return response()->json(['message' => 'Cannot delete default admin user'], 403);
        }
        $user->delete();

        return response()->json(['message' => "User {$user->name} deleted successfully"]);
    }

    public function getUserPermissions(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'user' => $user->name,
            'permissions' => $permissions,
        ]);
    }
}
