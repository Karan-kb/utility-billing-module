<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;
    protected $guard_name = 'api';
    protected $connection = 'mysql';


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id'
    ];



    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function assignRole($role)
    {
        $roleId = $role instanceof \Spatie\Permission\Models\Role ? $role->id : $role;

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => self::class,
            'model_id' => $this->id,
            'company_id' => $this->company_id
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }



    public function companyPermissions()
    {
        return $this->morphToMany(
            Permission::class,
            'model',
            'model_has_permissions',
            'model_id',
            'permission_id'
        )->withPivot('company_id');
    }
    // The company where the user is the admin
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'model_has_roles',
            'model_id',
            'role_id'
        );
    }


    /**
     * Check if user has a permission for a specific company.
     */
    public function hasOrganizationPermission(string $permission, ?int $companyId = null): bool
    {
        // Only organization role uses company-restricted permissions
        if (!$this->hasRole('organization')) {
            Log::debug("User ID {$this->id} does not have 'organization' role, checking global permission: {$permission}");
            return $this->hasPermissionTo($permission); // Global check for non-organization roles
        }

        // Always use the user's associated company ID from the hasOne relationship
        $companyId = $this->company?->id;
        // dd($companyId);

        if (!$companyId) {
            Log::warning("No company associated with user ID {$this->id} when checking permission: {$permission}");
            return false;
        }

        // Log if an external companyId was passed (for debugging)
        if ($companyId !== null && $companyId !== $this->company?->id) {
            Log::warning("External company ID {$companyId} passed for user ID {$this->id}, ignoring in favor of associated company ID {$this->company?->id}");
        }

        // Check the permission strictly for the user's company
        $hasPermission = DB::table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_type', self::class)
            ->where('model_has_permissions.model_id', $this->id)
            ->where('model_has_permissions.company_id', $companyId)
            ->where('permissions.name', $permission)
            ->exists();

        Log::debug("Permission check for user ID {$this->id}, company ID {$companyId}, permission '{$permission}': " . ($hasPermission ? 'Granted' : 'Denied'));

        return $hasPermission;
    }



    public function hasOrganizationRole(string $role, ?int $companyId = null): bool
    {
        $companyId = $companyId ?? $this->company?->id;

        if (!$companyId)
            return false;

        return \Spatie\Permission\Models\Role::where('name', $role)
            ->whereHas(
                'modelHasRoles',
                fn($q) => $q
                    ->where('model_type', self::class)
                    ->where('model_id', $this->id)
                    ->where('company_id', $companyId)
            )->exists();
    }

    public function rolesWithMenuPermissions()
    {
        return $this->roles()->with('permissions.menu');
    }

    public function hasMenuPermission(int $menuId, string $permissionType): bool
    {
        $roles = $this->roles()->with('roleMenuPermissions')->get();

        foreach ($roles as $role) {
            foreach ($role->roleMenuPermissions as $perm) {
                if ($perm->menu_id == $menuId && $perm->$permissionType) {
                    return true;
                }
            }
        }

        return false;
    }






}
