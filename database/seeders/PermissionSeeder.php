<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.panel',
            'org.panel',

            'applications.read',
            'applications.create',
            'applications.update',
            'applications.delete',

            'addresses.read',
            'addresses.create',
            'addresses.update',
            'addresses.delete',

            'email.read',
            'email.create',
            'email.update',
            'email.delete',

            'forms.read',
            'forms.create',
            'forms.update',
            'forms.delete',

            'users.read',
            'users.create',
            'users.update',
            'users.delete',

            'permissions.read',
            'permissions.create',
            'permissions.update',
            'permissions.delete',

            'roles.read',
            'roles.create',
            'roles.update',
            'roles.delete',

            'children.read',
            'children.create',
            'children.update',
            'children.delete',

            'languages.read',
            'languages.create',
            'languages.update',
            'languages.delete',

            'teams.read',
            'teams.create',
            'teams.update',
            'teams.delete',

            'pages.read',
            'pages.create',
            'pages.update',
            'pages.delete',
        ];

        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                ['name' => $name]
            );
        }
    }
}
