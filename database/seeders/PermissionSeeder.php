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

        // Roles & assign permissions to roles
        // Role::create(['name' => 'Admin'])->givePermissionTo(
        //     Permission::all()
        // );

        $role = Role::create(['name' => 'Super Admin']);
        $role = Role::create(['name' => 'Admin']);

        //Some initial role configuration
        // $roles = [
        //     'Admin' => [
        //         'view posts',
        //         'create posts',
        //         'update posts',
        //         'delete posts',
        //     ],
        //     'Editor' => [
        //         'view posts',
        //         'create posts',
        //         'update posts'
        //     ],
        //     'Member' => [
        //         'view posts'
        //     ]
        // ];

        // collect($roles)->each(function ($permissions, $role) {
        //     $role = Role::findOrCreate($role);
        //     collect($permissions)->each(function ($permission) use ($role) {
        //         $role->permissions()->save(Permission::findOrCreate($permission));
        //     });
        // });
    }
}
