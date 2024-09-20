<?php

namespace Tests;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function signIn($user = null)
    {
        $user = $user ?: User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    protected function signInWithPermissions($user = null, $permissions)
    {
        $user = $user ?: User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo((Permission::firstOrCreate(['name' => $permission])->id));
        }

        $this->actingAs($user);

        return $user;
    }

    protected function signInAsSuperAdmin($user = null)
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::where('name', 'Super Admin')->first() ?? Role::create(['name' => 'Super Admin']);
        $user = $this->signIn($user);
        $user->assignRole($role);

        return $user;
    }
}
