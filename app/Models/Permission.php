<?php


namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends SpatiePermission
{
    use SoftDeletes;

    // Force central DB for all permission operations
    protected $connection = 'mysql';

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($permission) {
            // Assign new permission to super admin role automatically
            $adminRole = Role::where('name', 'super admin')->first();
            if ($adminRole) {
                $adminRole->givePermissionTo($permission);
            }
        });
    }
}
