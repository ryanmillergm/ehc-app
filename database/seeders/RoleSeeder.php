<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::query()->get();

        $superAdmin = Role::query()->firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->syncPermissions($allPermissions);

        Role::query()->firstOrCreate(['name' => 'Admin']);
        Role::query()->firstOrCreate(['name' => 'Director']);
        Role::query()->firstOrCreate(['name' => 'Editor']);
    }
}
