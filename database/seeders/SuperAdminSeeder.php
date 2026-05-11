<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
       
        $role = Role::firstOrCreate([
            'name' => 'super admin',
            'guard_name' => 'api',
        ]);

        
        $user = User::firstOrCreate([
            'email' => 'superadmin@admin.com',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('P@ssw0RdisStrong321'), 
        ]);

        
        $user->assignRole($role);
    }
}
