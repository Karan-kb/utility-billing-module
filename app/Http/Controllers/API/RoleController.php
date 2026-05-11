<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;

use function Illuminate\Log\log;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->setupDefaultAdmin();
    }

    private function setupDefaultAdmin()
    {
        if (!Role::withoutTrashed()->where('name', 'admin')->exists()) {
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

            if (!User::withoutTrashed()->where('email', 'admin@gmail.com')->exists()) {
                $adminUser = User::create([
                    'name' => 'Admin User',
                    'email' => 'admin@gmail.com',
                    'password' => Hash::make('12345678'),
                ]);

                $adminUser->assignRole('admin');
            }
        }
    }

    public function createRole(Request $request)
    {

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'company_id' => 'required|integer|exists:companies,id',
                'is_active' => 'sometimes|boolean', // Allow manual setting of is_active
            ]);

            // Check if a non-deleted role with the same name exists
            $exists = Role::where('name', $request->name)
                ->where('company_id', $request->company_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Role already exists for this company.'
                ], 422);
            }

            $role = Role::create([
                'name' => $request->name,
                'company_id' => $request->company_id,
                'guard_name' => 'api',
                'is_active' => $request->has('is_active') ? $request->is_active : 1, // Default to 1 if not provided
            ]);

            return response()->json(['message' => "Role {$role->name} created successfully"], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while creating the role: ' . $e->getMessage()], 500);
        }
    }

    public function listRolesByCompany(Request $request, $companyId)
    {
        try {
            $company = \App\Models\Company::with(['roles' => function ($q) {
                $q->where('is_active', 1)->orderBy('name'); 
            }])->find($companyId);

            if (!$company) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Company not found'
                ], 404);
            }

            $result = [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'roles' => $company->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'is_active' => $role->is_active,
                    ];
                }),
            ];

            return response()->json([
                'status' => 'Roles for ' . $company->name . ' company fetched successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch roles for the company: ' . $e->getMessage()
            ], 500);
        }
    }



    public function Roleswithpermission(Request $request)
    {


        try {
            $roles = Role::withoutTrashed()
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->pluck('name'),
                        'is_active' => $role->is_active, // Include is_active in response
                    ];
                });

            return response()->json(['roles' => $roles]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing roles'], 500);
        }
    }

    public function listRoles(Request $request)
    {
        try {
            $entries = Role::withoutTrashed()->with('company'); 

            $paginated = $entries->paginate(10);

            $paginated->getCollection()->transform(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'company_id' => $role->company_id,
                    'company_name' => $role->company ? $role->company->name : null,
                    'is_active' => $role->is_active,
                ];
            });

            return response()->json($paginated);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing roles'], 500);
        }
    }


    public function listRolesonly(Request $request)
    {


        try {
            $roles = Role::withoutTrashed()
                ->where('is_active', 1)
                ->get()
                ->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                });

            return response()->json(['roles' => $roles]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing roles'], 500);
        }
    }

    public function editRole(Request $request)
    {
       

        try {
            $request->validate([
                'id' => 'required|exists:roles,id,deleted_at,NULL',
                'name' => 'required|string|max:255',
                'is_active' => 'sometimes|boolean', // Allow manual setting of is_active
            ]);

            $role = Role::withoutTrashed()->findOrFail($request->id);

           
            // if ($role->name === 'admin' || $role->name === 'user') {
            //     if ($request->has('is_active') && !$request->is_active) {
            //         return response()->json(['message' => 'Cannot deactivate default roles'], 403);
            //     }
            //     if ($request->name !== $role->name) {
            //         return response()->json(['message' => 'Cannot rename default roles'], 403);
            //     }
            // }
            if (Role::withoutTrashed()
            ->where('company_id', $role->company_id)
            ->where('name', $request->name)
            ->where('id', '!=', $role->id) 
            ->exists()) {
                return response()->json(['message' => "Role {$request->name} already exists"], 422);
            }

            $role->update([
                'name' => $request->name,
                'is_active' => $request->has('is_active') ? $request->is_active : $role->is_active, // Update is_active if provided
            ]);

            return response()->json(['message' => "Role updated to {$role->name} successfully"]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the role'], 500);
        }
    }

    public function deleteRole(Request $request,$id)
    {

        try {
            $request->merge(['id' => $id]);
            $request->validate([
                'id' => 'required|exists:roles,id,deleted_at,NULL',
            ]);

            $role = Role::withoutTrashed()->findOrFail($id);
            if ($role->name === 'admin' || $role->name === 'user') {
                return response()->json(['message' => 'Cannot delete default roles'], 403);
            }
            $role->delete();

            return response()->json(['message' => "Role {$role->name} deleted successfully"]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while deleting the role'], 500);
        }
    }

    public function detail($id)
    {
        try {          
            
            $roles = Role::withoutTrashed()->with('company')
            ->where('id','=', $id)
            ->get();         

            $data = $roles->map(function ($role) {
                return [
                    'id'           => $role->id,
                    'name'         => $role->name,
                    'company_id'   => $role->company_id,
                    'company_name' => $role->company->name ?? null, // Using null coalescing
                    'is_active'    => $role->is_active,
                ];
            });          

            return response()->json(['role' => $data]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred fetching the role'], 500);
        }
    }

    public function assignRole(Request $request)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        if ($user->company_id !== $role->company_id) {
            return response()->json([
                'message' => 'Role does not belong to the user company'
            ], 422);
        }

        $user->syncRoles([$role->id]);

        return response()->json([
            'message' => "Role '{$role->name}' assigned to {$user->name}"
        ]);
    }
}
