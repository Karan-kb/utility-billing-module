<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SetupTenantJob;
use App\Models\Company;
use App\Models\MasterSetup;
use App\Models\MasterSetupType;
use App\Models\Role;
use App\Models\RoleMenuPermission;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use App\Models\TariffSetup;
use App\Models\User;
use App\Stubs\MainGroupStub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Providers\TenantInitializer;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class CompanyController extends Controller
{



    public function createCompany(Request $request)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Only super admin can create a company'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reg_number' => ['required', 'string', 'max:20', Rule::unique('companies', 'reg_number')->whereNull('deleted_at')],
            'software_type' => ['nullable', 'integer', 'in:0,1'],
            'pan_number' => ['required', 'string', 'max:20', Rule::unique('companies', 'pan_number')->whereNull('deleted_at')],
            'license_number' => ['required', 'string', 'max:20', Rule::unique('companies', 'license_number')->whereNull('deleted_at')],
            'licence_issue_date' =>
                [
                    'required',
                    'date',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],

            'license_expiry_date' => [
                'required',
                'date',
                'after_or_equal:licence_issue_date',
                'after_or_equal:today',
            ],
            'full_address' => 'required|string',
            'email_address' => 'required|email|max:50',
            'website' => 'nullable|url|max:255',
            'contact_number' => 'required|numeric|digits_between:6,15',
            'province_id' => 'required|integer|exists:provinces,id',
            'district_id' => 'required|integer|exists:districts,id',
            'municipality_id' => 'required|integer|exists:municipalities,id',
            'ward_no' => 'required|integer',
            'contact_person' => 'required|string|max:50',
            'contact_person_position' => 'required|string|max:50',
            'activation_key' => 'required|string|max:50',
            'url_link' => 'nullable|url|max:255',
        ]);


        $connection = 'mysql';


        return DB::connection($connection)->transaction(function () use ($validated) {

            $company = Company::create([
                'name' => $validated['name'],
                'reg_number' => $validated['reg_number'],
                'pan_number' => $validated['pan_number'],
                'license_number' => $validated['license_number'],
                'licence_issue_date' => $validated['licence_issue_date'],
                'license_expiry_date' => $validated['license_expiry_date'],
                'full_address' => $validated['full_address'],
                'email_address' => $validated['email_address'] ?? null,
                'website' => $validated['website'] ?? null,
                'province_id' => $validated['province_id'],
                'district_id' => $validated['district_id'],
                'municipality_id' => $validated['municipality_id'],
                'ward_no' => $validated['ward_no'],
                'contact_number' => $validated['contact_number'],
                'contact_person' => $validated['contact_person'],
                'contact_person_position' => $validated['contact_person_position'],
                'activation_key' => $validated['activation_key'],
                'url_link' => $validated['url_link'] ?? null,
            ]);

            $sluggedName = Str::slug($company->name);
            $databaseName = "{$sluggedName}_{$company->id}";
            $counter = 1;

            while (Tenant::where('database', $databaseName)->exists()) {
                $databaseName = "{$sluggedName}_{$company->id}_{$counter}";
                $counter++;
            }

            $tenantId = (string) Str::uuid();
            $softwareType = $validated['software_type'] ?? 0;
            $tenant = Tenant::create([
                'id' => $tenantId,
                'database' => $databaseName,
                'company_id' => $company->id,
                'software_type' => $softwareType,
                'data' => json_encode([
                    'company_id' => $company->id,
                    'database' => $databaseName,
                    'software_type' => $softwareType,
                ]),
            ]);

            DB::afterCommit(function () use ($tenant, $databaseName, $company) {
                SetupTenantJob::dispatch(
                    $tenant->id,
                    $databaseName,
                    $company->id,
                );
            });

            return response()->json([
                'message' => "Company '{$company->name}' and tenant created successfully",
                'company' => $company,
                'tenant' => $tenant,
            ], 201);
        });
    }

    public function createCompanyUser(Request $request, $companyId)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Only super admin can create a company'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => 'required|string|confirmed|min:6',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::where('id', $request->role_id)
            ->where('company_id', $companyId)
            ->first();

        if (!$role) {
            return response()->json([
                'message' => 'Invalid role for this company'
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'company_id' => $companyId
        ]);

        $user->assignRole($role->id);

        return response()->json([
            'message' => 'User created and role assigned successfully',
            'user' => $user,
            'role' => $role
        ]);
    }

    public function enterCompany(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('super admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        $company = Company::findOrFail($request->company_id);
        $tenant = Tenant::where('company_id', $company->id)->firstOrFail();

        \App\Providers\TenantInitializer::switchTenant($tenant);

        $token = $user->createToken(
            'impersonation-token',
            ['impersonate', 'company:' . $company->id],
            now()->addHours(1)
        )->plainTextToken;

        return response()->json([
            'message' => 'Entered company successfully',
            'token' => $token,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
        ]);
    }

    public function exitCompany(Request $request)
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json(['message' => 'No token provided'], 400);
        }

        $token = PersonalAccessToken::findToken($bearer);

        if ($token) {
            $token->delete();
            return response()->json(['message' => 'Exited organization']);
        }

        return response()->json(['message' => 'Token not found'], 400);
    }


    public function listCompanies(Request $request)
    {
        if (!$request->user()->hasRole('super admin')) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Only super admin can view companies'], 403);
            }
            abort(403, 'Only super admin can view companies');
        }

        try {
            $query = Company::query();

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 10);
            $companies = $query->orderBy('id', 'desc')->paginate($perPage);
            $companies->getCollection()->transform(function ($company) {
                $tenant = \App\Models\Tenant::where('company_id', $company->id)->first();
                $company->software_type = ($tenant?->software_type ?? 0) === 0 ? 'Bidut' : 'Khanepani';
                return $company;
            });

            return response()->json([
                'message' => 'Companies fetched successfully',
                'companies' => $companies
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCompanyById(Request $request, $companyId)
    {
        if (!$request->user()->hasRole('super admin')) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Only super admin can view a company'], 403);
            }
            abort(403, 'Only super admin can view a company');
        }

        try {
            $company = Company::findOrFail($companyId);

            $adminUser = $company->user_id ? User::find($company->user_id) : null;

            $companyData = $company->toArray();
            if ($adminUser) {
                $companyData['admin_id'] = $adminUser->id;
                $companyData['admin_name'] = $adminUser->name;
                $companyData['admin_email'] = $adminUser->email;
            }

            return response()->json([
                'message' => 'Company fetched successfully',
                'company' => $companyData
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }







    public function updateCompany(Request $request, $companyId)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Only super admin can update a company'], 403);
        }


        $connection = 'mysql';
        try {
            $company = Company::find($companyId);
            if (!$company) {
                return response()->json(['message' => 'Company not found'], 404);
            }

            $tenant = Tenant::where('company_id', $company->id)->first();
            $adminUser = $company->user_id ? User::find($company->user_id) : null;

            $messages = [
                'password.regex' => 'Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character (!@#$%^&*).'
            ];

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],


                'reg_number' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('companies', 'reg_number')->ignore($company->id)->whereNull('deleted_at')
                ],
                'pan_number' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('companies', 'pan_number')->ignore($company->id)->whereNull('deleted_at')
                ],
                'license_number' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('companies', 'license_number')->ignore($company->id)->whereNull('deleted_at')
                ],
                'licence_issue_date' =>
                    [
                        'required',
                        'date',
                        function ($attribute, $value, $fail) {
                            $today = date('Y-m-d');
                            if ($value > $today) {
                                $fail('The ' . $attribute . ' cannot be a future date.');
                            }
                        },
                    ],

                'license_expiry_date' => [
                    'required',
                    'date',
                    'after_or_equal:licence_issue_date',
                    'after_or_equal:today',
                ],
                'full_address' => 'required|string',
                'email_address' => 'required|email|max:50',
                'website' => 'nullable|url|max:255',
                'contact_number' => 'required|numeric|digits_between:6,15',
                'province_id' => 'required|integer|exists:provinces,id',
                'district_id' => 'required|integer|exists:districts,id',
                'municipality_id' => 'required|integer|exists:municipalities,id',
                'ward_no' => 'required|integer',
                'contact_person' => 'required|string|max:50',
                'contact_person_position' => 'required|string|max:50',
                'url_link' => 'nullable|url|max:255',
            ], $messages);

            if ($request->has('activation_key')) {
                $newKey = $request->input('activation_key');
                if ($newKey !== $company->activation_key) {
                    return response()->json([
                        'message' => 'Activation key cannot be changed.'
                    ], 422);
                }
            }

            // DB::beginTransaction();
            return DB::connection($connection)->transaction(function () use ($company, $tenant, $adminUser, $validated) {


                $company->update(Arr::except($validated, ['activation_key']));



                // Update admin user details
                if ($adminUser) {
                    $adminUser->update([
                        'name' => $validated['admin_name'],
                        'email' => $validated['admin_email'],
                        'password' => !empty($validated['password'])
                            ? Hash::make($validated['password'])
                            : $adminUser->password,
                    ]);
                }


                return response()->json([
                    'message' => "Company '{$company->name}' updated successfully",
                    'company' => $company,
                    'tenant' => $tenant,
                    'admin_user' => $adminUser,
                ], 200);
            });
        } catch (ValidationException $e) {
            $errorMessage = collect($e->errors())->flatten()->first();
            return response()->json([
                'message' => "$errorMessage",
                'error' => $errorMessage,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Error updating company: {$e->getMessage()}",
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function deleteCompany(Request $request, $companyId)
    {
        if (!$request->user()->hasRole('super admin')) {
            return response()->json(['message' => 'Only super admin can delete a company'], 403);
        }

        try {
            $company = Company::findOrFail($companyId);
            $tenant = Tenant::where('company_id', $company->id)->first();

            $backupFile = null;

            if ($tenant) {
                $tenantDbName = $tenant->database;

                $databaseExists = DB::select("SHOW DATABASES LIKE '{$tenantDbName}'");

                if (!empty($databaseExists)) {

                    $backupFolder = storage_path('app/company_backups');
                    if (!file_exists($backupFolder)) {
                        mkdir($backupFolder, 0755, true);
                    }


                    foreach (glob($backupFolder . '/*.sql') as $file) {
                        if (filemtime($file) < now()->subDays(7)->timestamp) {
                            @unlink($file);
                        }
                    }


                    $timestamp = now()->format('Y_m_d_His');
                    $backupFile = "{$backupFolder}/{$tenantDbName}backup{$timestamp}.sql";

                    $dbUser = env('DB_BACKUP_USER');
                    $dbPass = env('DB_BACKUP_PASSWORD');
                    $dbHost = env('DB_BACKUP_HOST', '127.0.0.1');
                    $mysqldumpPath = env('MYSQLDUMP_PATH', '/usr/bin/mysqldump');

                    $command = "\"{$mysqldumpPath}\" -h {$dbHost} -u {$dbUser} " .
                        (!empty($dbPass) ? "-p'{$dbPass}' " : "") .
                        "--result-file=\"{$backupFile}\" {$tenantDbName}";

                    exec($command . ' 2>&1', $output, $returnVar);

                    if ($returnVar !== 0) {
                        return response()->json([
                            'message' => 'Database backup failed. Company not deleted.',
                            'error' => implode("\n", $output),
                            'command' => $command,
                        ], 500);
                    }
                }

                DB::statement("DROP DATABASE IF EXISTS `{$tenantDbName}`");
                $tenant->delete();
            } else {
                Tenant::where('company_id', $company->id)->delete();
            }

            User::where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->delete();
            Role::where ('company_id', $company->id)->delete();
           $roleIds = Role::withTrashed()
                ->where('company_id', $company->id)
                ->pluck('id');

            RoleMenuPermission::whereIn('role_id', $roleIds)->delete();

            return response()->json([
                'message' => "Company '{$company->name}' deleted successfully !!",
                'backup_file' => $backupFile,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Company not found !'], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting company',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function getCompany(Request $request)
    // {

    //     try {

    //         $user = Auth::user();
            

    //         $companyId = User::find($user->id)->company_id;


    //         $companyDetails = Company::select('id','name','full_address')->where('id', $companyId)->first();
    //         $companyDetails = $companyDetails->toArray(); // convert to array
    //         $companyDetails['user_name'] = $user->name;
    //                 $companyDetails['software_type'] = TenantRuntimeHelper::currentSoftwareType();



    //         return response()->json([
    //             'message' => 'Company details fetched successfully',
    //             'company' => $companyDetails
    //         ], 200);


    //     } catch (ModelNotFoundException $e) {
    //         return response()->json(['message' => 'Company not found'], 404);
    //     } catch (QueryException $e) {
    //         return response()->json(['message' => 'Database query error', 'error' => $e->getMessage()], 500);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => 'An unexpected error occurred', 'error' => $e->getMessage()], 500);
    //     }

    // }
public function getCompany(Request $request)
{
    try {
        $user = Auth::user();

        // Master admin: get company_id from token abilities
        if ($user->hasRole('master_admin')) {
            $companyId = null;
            $token = PersonalAccessToken::findToken($request->bearerToken());
            if ($token) {
                foreach ($token->abilities as $ability) {
                    if (strpos($ability, 'company:') === 0) {
                        $companyId = (int) substr($ability, 8);
                        break;
                    }
                }
            }
        } else {
            $companyId = User::find($user->id)->company_id;
        }

        if (!$companyId) {
            return response()->json(['message' => 'No company associated with this user'], 404);
        }

        $companyDetails = Company::select('id', 'name', 'full_address')
            ->where('id', $companyId)
            ->first();

        if (!$companyDetails) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $companyDetails = $companyDetails->toArray();
        $companyDetails['user_name'] = $user->name;
        $companyDetails['software_type'] = TenantRuntimeHelper::currentSoftwareType();

        return response()->json([
            'message' => 'Company details fetched successfully',
            'company' => $companyDetails
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Company not found'], 404);
    } catch (QueryException $e) {
        return response()->json(['message' => 'Database query error', 'error' => $e->getMessage()], 500);
    } catch (\Exception $e) {
        return response()->json(['message' => 'An unexpected error occurred', 'error' => $e->getMessage()], 500);
    }
}



}
