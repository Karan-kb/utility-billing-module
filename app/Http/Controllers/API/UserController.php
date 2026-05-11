<?php

namespace App\Http\Controllers\API;

use App\Helpers\LoginActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function listUsers(Request $request)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Only super admin can see user list'], 403);
        }

        try {
            $entries = User::withoutTrashed();
            return response()->json($entries->paginate(50));
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing users'], 500);
        }
    }

    public function listCompanyUsers(Request $request, int $companyId)
    {
        $authUser = $request->user();
        if (
            !$authUser->hasRole('super admin') &&
            $authUser->company_id !== $companyId
        ) {
            return response()->json([
                'message' => 'You cannot access users of another company'
            ], 403);
        }

        try {
            $users = User::withoutTrashed()
                ->where('company_id', $companyId)
                ->with(['roles:id,name'])
                ->select('id', 'company_id', 'name', 'email')
                ->paginate(10);

            $users->getCollection()->transform(function ($user) {
                $user->roles->each->makeHidden('pivot');
                return $user;
            });

            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to list company users'
            ], 500);
        }
    }


    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|exists:roles,name,deleted_at,NULL',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $role = Role::withoutTrashed()->where('name', $validated['role'])->first();
            $user->assignRole($role->name);

            $token = $user->createToken('auth_token')->plainTextToken;
           //LoginActivityLogger::log($user->id, 'register');
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'role' => $role->name,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while registering the user'], 500);
        }
    }


    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (!Auth::attempt($credentials)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            $user = Auth::user();
            // ─── MASTER ADMIN FLOW ───────────────────────────────────────────
                if ($user->hasRole('master_admin')) {
                       $roles = $user->roles()
                    ->get(['roles.id', 'roles.name'])
                    ->makeHidden('pivot');

                        return response()->json([
                            'requires_company_selection' => true,
                            'master_admin_user_id'       => $user->id,
                            'user'                       => $user->name,
                            'roles'                      => $roles,
                            'message'                    => 'Select software type and company to continue',
                        ], 200);
                    }

            // Remove old tokens
            $user->tokens()->delete();
            RefreshToken::where('user_id', $user->id)->delete();


            if ($user->hasRole('organization')) {
                $company = $user->company;

                if (!$company) {
                    return response()->json(['message' => 'No company associated with this user'], 403);
                }

                $tenant = \App\Models\Tenant::where('company_id', $company->id)->first();
                
                if (!$tenant) {
                    return response()->json(['message' => 'Tenant not found'], 404);
                }


                $databaseName = $tenant->database ?? ($tenant->data['database'] ?? null);
                if (empty($databaseName)) {
                    return response()->json(['message' => 'Tenant database not defined'], 500);
                }

                try {
                    \App\Providers\TenantInitializer::switchTenant($tenant);

                } catch (\Exception $e) {

                    return response()->json(['message' => 'Failed to switch to tenant database'], 500);
                }

                $expiry = $company->license_expiry_date;
                if (!$expiry) {
                    return response()->json(['message' => 'No license expiry date set for the company'], 403);
                }

                $expiryDate = Carbon::parse($expiry)->endOfDay();
                if (Carbon::now()->gt($expiryDate)) {

                    $user->tokens()->delete();
                    return response()->json(['message' => 'Your company license has expired'], 403);
                }
            }

            // Create access token
            $tokenResult = $user->createToken('auth_token');
            $accessTokenRecord = $tokenResult->accessToken;
            $accessTokenRecord->expires_at = now()->addDays(28);
            $accessTokenRecord->save();
            $accessToken = $tokenResult->plainTextToken;

            $refreshTokenPlain = Str::random(64);
            RefreshToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', $refreshTokenPlain),
                'expires_at' => now()->addDays(30),
            ]);

            $roles = \App\Models\Role::where('company_id', $user->company_id)
                ->whereHas('users', function ($q) use ($user) {
                    $q->where('model_id', $user->id);
                })
                ->get(['id', 'name']);
            LoginActivityLogger::log($user->id, 'login');
            $softwareType = \App\Helpers\TenantRuntimeHelper::currentSoftwareType();
            return response()->json([
                'access_token' => $accessToken,
                'refresh_token' => $refreshTokenPlain,
                'token_type' => 'Bearer',
                'expires_in' => 36000,
                'user' => $user->name,
                'roles' => $roles,
                'software_type' => $softwareType,
                'message' => 'Successfully logged in',
            ], 200);

        } catch (ValidationException $e) {

            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while logging in !',
                'error' => $e->getMessage()
            ], 500);
        }
    }


// public function masterAdminSelectCompany(Request $request, $user_id, $software_type, $company_id)
// {
//     try {
//         if (!in_array($software_type, [0, 1])) {
//             return response()->json(['message' => 'Invalid software type'], 422);
//         }


//         $user = \App\Models\User::findOrFail($request->user_id);

//         if (!$user->hasRole('master_admin')) {
//             return response()->json(['message' => 'Unauthorized'], 403);
//         }

//         // Confirm the chosen company has the right software_type
//         $tenant = \App\Models\Tenant::where('company_id', $request->company_id)
//             ->where('software_type', $request->software_type)
//             ->first();

//         if (!$tenant) {
//             return response()->json([
//                 'message' => 'Company not found for the selected software type'
//             ], 404);
//         }

//         // Check license
//         $company = \App\Models\Company::findOrFail($request->company_id);
 
       
//         $user->company_id = $company->id;

//         // Issue tokens
//         $user->tokens()->delete();
//         \App\Models\RefreshToken::where('user_id', $user->id)->delete();

//         //$tokenResult      = $user->createToken('auth_token');
//         $tokenResult = $user->createToken('auth_token', ['master_admin', 'company:' . $company->id]);
//         $accessTokenRecord = $tokenResult->accessToken;
//         $accessTokenRecord->expires_at = now()->addDays(28);
//         $accessTokenRecord->save();
//         $accessToken = $tokenResult->plainTextToken;

//         $refreshTokenPlain = \Illuminate\Support\Str::random(64);
//         \App\Models\RefreshToken::create([
//             'user_id'    => $user->id,
//             'token'      => hash('sha256', $refreshTokenPlain),
//             'expires_at' => now()->addDays(30),
//         ]);

//        \App\Helpers\LoginActivityLogger::log($user->id, 'login');
//         \App\Providers\TenantInitializer::switchTenant($tenant);

//           $softwareType = \App\Helpers\TenantRuntimeHelper::currentSoftwareType();

//         return response()->json([
//             'access_token'  => $accessToken,
//             'refresh_token' => $refreshTokenPlain,
//             'token_type'    => 'Bearer',
//             'expires_in'    => 36000,
//             'user'          => $user->name,
//             'software_type' => $softwareType,
//             'company_id'    => $company->id,
//             'company_name'  => $company->name,
//             'message'       => 'Successfully logged in as master admin',
//         ], 200);

//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
//     } catch (\Exception $e) {
//         return response()->json(['message' => 'Login failed', 'error' => $e->getMessage()], 500);
//     }
// }
public function masterAdminSelectCompany(Request $request, $user_id, $software_type, $company_id)
{
    try {

        if (!in_array($software_type, [0, 1])) {
            return response()->json([
                'message' => 'Invalid software type'
            ], 422);
        }

        $user = \App\Models\User::findOrFail($user_id);

        if (!$user->hasRole('master_admin')) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        // Confirm the chosen company has the correct software type
        $tenant = \App\Models\Tenant::where('company_id', $company_id)
            ->where('software_type', $software_type)
            ->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Company not found for the selected software type'
            ], 404);
        }

        // Get company
        $company = \App\Models\Company::findOrFail($company_id);

        // Assign company to master admin
        $user->company_id = $company->id;
        $user->save();

        // Remove old tokens
        $user->tokens()->delete();

        \App\Models\RefreshToken::where('user_id', $user->id)->delete();

        // Switch tenant
        \App\Providers\TenantInitializer::switchTenant($tenant);

        // Create access token
        $tokenResult = $user->createToken(
            'auth_token',
            ['master_admin', 'company:' . $company->id]
        );

        $accessTokenRecord = $tokenResult->accessToken;
        $accessTokenRecord->expires_at = now()->addDays(28);
        $accessTokenRecord->save();

        $accessToken = $tokenResult->plainTextToken;

        // Create refresh token
        $refreshTokenPlain = \Illuminate\Support\Str::random(64);

        \App\Models\RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $refreshTokenPlain),
            'expires_at' => now()->addDays(30),
        ]);

        // Get roles in same format
        $roles = $user->roles()
            ->get(['roles.id', 'roles.name'])
            ->makeHidden('pivot');

        \App\Helpers\LoginActivityLogger::log($user->id, 'login');

        $softwareType = \App\Helpers\TenantRuntimeHelper::currentSoftwareType();

        return response()->json([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshTokenPlain,
            'token_type'    => 'Bearer',
            'expires_in'    => 36000,
            'user'          => $user->name,
            'roles'         => $roles,
            'software_type' => $softwareType,
            'company_id'    => $company->id,
            'company_name'  => $company->name,
            'message'       => 'Successfully logged in',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'message' => $e->getMessage(),
            'errors'  => $e->errors()
        ], 422);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Login failed',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function getCompaniesBySoftwareType(Request $request, $user_id, $software_type)
{
    if (!in_array($software_type, [0, 1])) {
        return response()->json([
            'message' => 'Invalid software type'
        ], 422);
    }

    $user = User::findOrFail($user_id);

    if (!$user->hasRole('master_admin')) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    $search = $request->search;

    $companies = Tenant::where('software_type', $software_type)
        ->whereHas('company', function ($query) use ($search) {

            if (!empty($search)) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            }

        })
        ->with('company:id,name')
        ->get()
        ->map(function ($tenant) {
            return [
                'company_id'   => $tenant->company_id,
                'company_name' => $tenant->company->name ?? 'N/A',
            ];
        });

    return response()->json([
        'companies' => $companies
    ], 200);
}

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $hashedToken = hash('sha256', $request->refresh_token);

        $tokenRecord = RefreshToken::where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $user = $tokenRecord->user;

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->delete();

        $tokenResult = $user->createToken('auth_token');
        $accessTokenRecord = $tokenResult->accessToken;
        $accessTokenRecord->expires_at = now()->addDays(28);
        $accessTokenRecord->save();

        $accessToken = $tokenResult->plainTextToken;

        $refreshTokenPlain = Str::random(64);
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $refreshTokenPlain),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshTokenPlain,
            'token_type' => 'Bearer',
            'expires_in' => 15 * 60,
            'message' => 'Access and refresh tokens refreshed successfully',
        ], 200);
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            RefreshToken::where('user_id', $user->id)->delete();
            LoginActivityLogger::log($user->id, 'logout');
            return response()->json([
                'message' => 'Logged out successfully'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while logging out',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function UsersListwithPermission(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $users = User::withoutTrashed()->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ];
            });

            return response()->json(['users' => $users]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing users'], 500);
        }
    }

    public function editUser($id, Request $request)
    {



        try {
            $request->validate([

                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $request->id . ',id,deleted_at,NULL',
                'password' => 'sometimes|string|min:8|confirmed',
                'role_id' => 'nullable|integer'
            ]);

            $user = User::withoutTrashed()->findOrFail($id);

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
            if ($request->input('role_id')) {

                DB::table('model_has_roles')
                    ->where('model_id', $user->id)

                    ->update(['role_id' => $request->role_id]);

            }


            return response()->json(['message' => "User {$user->name} updated successfully"]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            
            return response()->json(['message' => 'An error occurred while updating the user'], 500);
        }
    }

    public function deleteUser($id,Request $request)
    {
        try {
           

            $user = User::withoutTrashed()->findOrFail($id);
            if ($user->email === 'admin@gmail.com') {
                return response()->json(['message' => 'Cannot delete default admin user'], 403);
            }
            $user->delete();

            return response()->json(['message' => "User {$user->name} deleted successfully"]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while deleting the user'], 500);
        }
    }


    public function detail($id)
    {



        try {



            $user = User::withoutTrashed()->with('company', 'roles')->findOrFail($id);
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'company_id' => $user->company_id,
                'company_name' => $user->company->name ?? null,
            ];



            return response()->json(['user' => $user]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while fetching the user'], 500);
        }
    }

    public function getUserPermissions(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view users')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $request->validate([
                'email' => 'required|email|exists:users,email,deleted_at,NULL',
            ]);

            $user = User::withoutTrashed()->where('email', $request->email)->first();
            $permissions = $user->getAllPermissions()->pluck('name');

            return response()->json([
                'user' => $user->name,
                'permissions' => $permissions,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while retrieving user permissions'], 500);
        }
    }
}
