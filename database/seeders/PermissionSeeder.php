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

        Permission::create(['name' => 'applications.read']);
        Permission::create(['name' => 'applications.create']);
        Permission::create(['name' => 'applications.update']);
        Permission::create(['name' => 'applications.delete']);

        Permission::create(['name' => 'addresses.read']);
        Permission::create(['name' => 'addresses.create']);
        Permission::create(['name' => 'addresses.update']);
        Permission::create(['name' => 'addresses.delete']);

        Permission::create(['name' => 'email.read']);
        Permission::create(['name' => 'email.create']);
        Permission::create(['name' => 'email.update']);
        Permission::create(['name' => 'email.delete']);

        Permission::create(['name' => 'users.read']);
        Permission::create(['name' => 'users.create']);
        Permission::create(['name' => 'users.update']);
        Permission::create(['name' => 'users.delete']);

        Permission::create(['name' => 'permissions.read']);
        Permission::create(['name' => 'permissions.create']);
        Permission::create(['name' => 'permissions.update']);
        Permission::create(['name' => 'permissions.delete']);

        Permission::create(['name' => 'roles.read']);
        Permission::create(['name' => 'roles.create']);
        Permission::create(['name' => 'roles.update']);
        Permission::create(['name' => 'roles.delete']);

        Permission::create(['name' => 'children.read']);
        Permission::create(['name' => 'children.create']);
        Permission::create(['name' => 'children.update']);
        Permission::create(['name' => 'children.delete']);

        Permission::create(['name' => 'languages.read']);
        Permission::create(['name' => 'languages.create']);
        Permission::create(['name' => 'languages.update']);
        Permission::create(['name' => 'languages.delete']);

        Permission::create(['name' => 'teams.read']);
        Permission::create(['name' => 'teams.create']);
        Permission::create(['name' => 'teams.update']);
        Permission::create(['name' => 'teams.delete']);

        Permission::create(['name' => 'pages.read']);
        Permission::create(['name' => 'pages.create']);
        Permission::create(['name' => 'pages.update']);
        Permission::create(['name' => 'pages.delete']);
    }
}
