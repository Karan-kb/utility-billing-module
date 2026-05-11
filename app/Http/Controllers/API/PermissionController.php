<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{



    public function listPermissions(Request $request)
    {


        try {
            $entries = Permission::query()->get();
            return response()->json($entries);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing permissions'], 500);
        }
    }



    public function createPermission(Request $request)
    {


        try {

            $hardcodedPermissions = [
                // "view roles", "create roles", "edit roles", "delete roles",
                // "view permissions", "create permissions", "edit permissions", "delete permissions",
                // "view users", "create users", "edit users", "delete users",
                "view master setups", "create master setups", "edit master setups", "delete master setups",
                "view member entries", "create member entries", "edit member entries", "delete member entries",
                "view meter issues", "create meter issues", "edit meter issues", "delete meter issues",
                "view other charges", "create other charges", "edit other charges", "delete other charges",
                "view discounts and fines", "create discounts and fines", "edit discounts and fines", "delete discounts and fines",
                "view rates and capacities", "create rates and capacities", "edit rates and capacities", "delete rates and capacities",
                "view tariff setup", "create tariff setup", "edit tariff setup", "delete tariff setup",
                "view meter reading entries", "create meter reading entries", "edit meter reading entries", "delete meter reading entries",
                "view other income receipts", "create other income receipts", "edit other income receipts", "delete other income receipts",
                "transfer entries", "view transfer entries",
                "view opening meter deposit entries", "create opening meter deposit entries", "edit opening meter deposit entries", "delete opening meter deposit entries",
                "view share opening entries", "create share opening entries", "edit share opening entries", "delete share opening entries",
                "view opening mahasul balance entries", "create opening mahasul balance entries", "edit opening mahasul balance entries", "delete opening mahasul balance entries",
                "view change meter issues", "change meter issues", "edit change meter issues", "delete change meter issues",
                "view share entries", "create share entries", "edit share entries", "delete share entries",
                "view deposit entries", "create deposit entries", "edit deposit entries", "delete deposit entries",
                "view upgrade meter capacity", "create upgrade meter capacity", "edit upgrade meter capacity", "delete upgrade meter capacity",
                "view share return", "create share return", "edit share return", "delete share return",
                "view deposit return", "create deposit return", "edit deposit return", "delete deposit return",
                "view mahsul receipts", "create mahsul receipts", "edit mahsul receipts", "delete mahsul receipts",
                "view meter insurance", "create meter insurance", "edit meter insurance", "delete meter insurance",
                "view advance payment", "create advance payment", "edit advance payment", "delete advance payment",
                "view non member payment", "create non member payment", "edit non member payment", "delete non member payment",
                "view fine post", "create fine post", "edit fine post", "delete fine post",
                "view nea purchase", "create nea purchase", "edit nea purchase", "delete nea purchase",
                "view nea payment", "create nea payment", "edit nea payment", "delete nea payment",
                "view measure units", "create measure units", "edit measure units", "delete measure units",
                "view main groups", "create main groups", "edit main groups", "delete main groups",
                "view sub groups", "create sub groups", "edit sub groups", "delete sub groups",
                "view account groups", "create account groups", "edit account groups", "delete account groups",
                "view account heads", "create account heads", "edit account heads", "delete account heads",
                "view fixed asset groups", "create fixed asset groups", "edit fixed asset groups", "delete fixed asset groups",
                "view fixed asset accounts", "create fixed asset accounts", "edit fixed asset accounts", "delete fixed asset accounts",
                "view voucher summaries", "create voucher summaries", "edit voucher summaries", "delete voucher summaries",
                "view voucher summary details", "create voucher summary details", "edit voucher summary details", "delete voucher summary details",
                "view purchases", "create purchases", "edit purchases", "delete purchases",
                "view products", "create products", "edit products", "delete products",
                "view product categories", "create product categories", "edit product categories", "delete product categories",
                "view product subcategories", "create product subcategories", "edit product subcategories", "delete product subcategories",
                "view purchase returns", "create purchase returns", "edit purchase returns", "delete purchase returns",
                "view sales", "create sales", "edit sales", "delete sales",
                "view sale returns", "create sale returns", "edit sale returns", "delete sale returns",
                "view member report", "view other income head report",   "view sales return",
                "create sales return",
                "edit sales return",
                "delete sales return"
            ];

            foreach ($hardcodedPermissions as $perm) {
                Permission::firstOrCreate([
                    'name' => $perm,
                    'guard_name' => 'api'
                ]);
            }

            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('permissions', 'name')->whereNull('deleted_at')
                ],
            ]);

            $permission = Permission::firstOrCreate([
                'name' => $request->name,
                'guard_name' => 'api'
            ]);

            return response()->json(['message' => "Permission {$permission->name} created successfully"], 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['name']) && in_array('The name has already been taken.', $errors['name'])) {
                return response()->json(['message' => "Permission {$request->name} already exists"], 422);
            }
            return response()->json(['message' => $e->getMessage(), 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while creating the permission'], 500);
        }
    }



    public function PermissionsListinJson(Request $request)
    {

        try {
            $permissions = Permission::all()->pluck('name');
            return response()->json(['permissions' => $permissions]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing permissions'], 500);
        }
    }

    public function editPermission(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('edit permissions')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $request->validate([
                'id' => 'required|exists:permissions,id',
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('permissions', 'name')->whereNull('deleted_at')->ignore($request->id)
                ],
            ]);

            $permission = Permission::findOrFail($request->id);
            $permission->update(['name' => $request->name]);

            return response()->json(['message' => "Permission updated to {$permission->name} successfully"]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['name']) && in_array('The name has already been taken.', $errors['name'])) {
                return response()->json(['message' => "Permission {$request->name} already exists"], 422);
            }
            return response()->json(['message' => $e->getMessage(), 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the permission'], 500);
        }
    }

    public function deletePermission(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('delete permissions')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $request->validate([
                'id' => 'required|exists:permissions,id',
            ]);

            $permission = Permission::findOrFail($request->id);
            $permission->delete();

            return response()->json(['message' => "Permission {$permission->name} deleted successfully"]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while deleting the permission'], 500);
        }
    }



    public function assignPermissionToRole(Request $request)
    {
        

        try {
            $validated = $request->validate([
                'role_id' => 'required|integer|exists:roles,id',
                'permission_ids' => 'required|array|min:1',
                'permission_ids.*' => 'integer|exists:permissions,id',
            ]);

            $role = Role::findOrFail($validated['role_id']);
            $permissions = Permission::whereIn('id', $validated['permission_ids'])->get();

            if ($permissions->isEmpty()) {
                return response()->json(['message' => 'No valid permissions found for assignment'], 422);
            }

            $role->syncPermissions($permissions);



            return response()->json([
                'message' => "Permissions with IDs assigned to role {$role->name}",
                'data' => [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'permissions' => $permissions->pluck('name')->toArray(),
                ],
            ], 200);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors)[0] ?? 'Validation failed';


        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while assigning permissions to the role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignPermissionsToCompanyOrganization(Request $request)
    {
        // if (!$request->user()->hasRole('super admin')) {
        //     return response()->json(['message' => 'Only super admin can assign permissions'], 403);
        // }

        try {
            $validated = $request->validate([
                'company_id' => 'required|integer|exists:companies,id',
                'permission_ids' => 'required|array|min:1',
                'permission_ids.*' => 'integer|exists:permissions,id',
            ]);

            $company = Company::findOrFail($validated['company_id']);
            // $adminUser = User::findOrFail($company->admin_user_id);
            $adminUser = User::findOrFail($company->user_id);

            $adminUser->companyPermissions()->sync(
                collect($validated['permission_ids'])
                    ->mapWithKeys(fn($id) => [$id => ['company_id' => $company->id]])
                    ->toArray()
            );

            $permissions = \App\Models\Permission::whereIn('id', $validated['permission_ids'])->get();

            return response()->json([
                'message' => "Permissions assigned to company {$company->name} organization admin",
                'company_id' => $company->id,
                'admin_user' => $adminUser->only(['id', 'name', 'email']),
                'permissions' => $permissions->pluck('name')->toArray(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while assigning permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getAllCompaniesPermissions()
    {
        $companies = Company::with('admin')->get();

        $data = $companies->map(function ($company) {
            if (!$company->admin) {
                return [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'permissions' => [],
                ];
            }

            $permissions = $company->admin->companyPermissions()
                ->wherePivot('company_id', $company->id)
                ->pluck('name');

            return [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'permissions' => $permissions,
            ];
        });

        return response()->json($data);
    }

    public function getCompanyPermissions(int $companyId)
    {
        $company = Company::findOrFail($companyId);
        // $adminUser = User::findOrFail($company->admin_user_id);
        $adminUser = User::findOrFail($company->user_id);

        $permissions = $adminUser->companyPermissions()
            ->wherePivot('company_id', $company->id)
            ->get(['id', 'name'])
            ->makeHidden('pivot');


        return response()->json($permissions);
    }












    public function editPermissionToRole(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('edit roles')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $request->validate([
                'role' => 'required|string|exists:roles,name',
                'permissions' => 'required|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            $role = Role::findByName($request->role, 'api');
            $existingPermissions = $role->permissions->pluck('name')->toArray();
            $addedPermissions = [];

            foreach ($request->permissions as $permissionName) {
                if (!in_array($permissionName, $existingPermissions)) {
                    $permission = Permission::findByName($permissionName, 'api');
                    $role->givePermissionTo($permission);
                    $addedPermissions[] = $permissionName;
                }
            }

            // Refresh permissions to ensure accurate response
            $role->refresh();
            $currentPermissions = $role->permissions->pluck('name')->values()->toArray();

            return response()->json([
                'message' => count($addedPermissions) > 0
                    ? "Permissions " . implode(', ', $addedPermissions) . " added to role {$role->name}"
                    : "No new permissions were added to role {$role->name}",
                'current_permissions' => $currentPermissions
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while editing permissions for the role',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
