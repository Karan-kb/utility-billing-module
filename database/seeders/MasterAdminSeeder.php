<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class MasterAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'master_admin',
            'guard_name' => 'api',
        ]);

        $user = User::firstOrCreate(
            ['email' => 'masteradmin@gmail.com'],
            [
                'name' => 'Master Admin',
                'password' => Hash::make('master!321321'),
            ]
        );

        if (!$user->hasRole('master_admin')) {
            $user->assignRole($role);
        }
    }
}