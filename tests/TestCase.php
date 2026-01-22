<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Filament\Facades\Filament;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Spatie: clear cached roles/permissions for deterministic tests
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function signIn($user = null)
    {
        $user = $user ?: User::factory()->create();

        $guard = Filament::getDefaultPanel()?->getAuthGuard() ?? 'web';
        $this->actingAs($user, $guard);

        return $user;
    }

    protected function signInWithPermissions($user = null, array $permissions = [])
    {
        $user = $user ?: User::factory()->create();

        $guard = Filament::getDefaultPanel()?->getAuthGuard() ?? 'web';

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name'       => $permissionName,
                'guard_name' => $guard,
            ]);

            $user->givePermissionTo($permission);
        }

        $this->actingAs($user, $guard);

        return $user;
    }

    protected function signInAsSuperAdmin($user = null)
    {
        $guard = Filament::getDefaultPanel()?->getAuthGuard() ?? 'web';

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            'name'       => 'Super Admin',
            'guard_name' => $guard,
        ]);

        $user = $this->signIn($user);
        $user->assignRole($role);

        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
