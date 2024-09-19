<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'admin.panel']);
        Permission::create(['name' => 'org.panel']);

        Permission::create(['name' => 'users.read']);
        Permission::create(['name' => 'users.write']);
        Permission::create(['name' => 'users.delete']);

        Permission::create(['name' => 'permissions.read']);
        Permission::create(['name' => 'permissions.write']);
        Permission::create(['name' => 'permissions.delete']);

        Permission::create(['name' => 'roles.read']);
        Permission::create(['name' => 'roles.write']);
        Permission::create(['name' => 'roles.delete']);

        Permission::create(['name' => 'teams.read']);
        Permission::create(['name' => 'teams.write']);
        Permission::create(['name' => 'teams.delete']);

        Permission::create(['name' => 'children.read']);
        Permission::create(['name' => 'children.write']);
        Permission::create(['name' => 'children.delete']);
    }
}
