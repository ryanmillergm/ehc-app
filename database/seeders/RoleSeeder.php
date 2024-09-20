<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Roles & assign permissions to roles
        Role::create(['name' => 'Super Admin'])->givePermissionTo(
            Permission::all()
        );

        $role = Role::create(['name' => 'Admin']);
        $role = Role::create(['name' => 'Director']);
        $role = Role::create(['name' => 'Editor']);
    }
}
