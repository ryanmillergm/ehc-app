<?php

namespace Tests\Feature\Spatie;

use App\Models\Role;
use ErrorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Creating a permission requires a name
     */
    public function test_permission_requires_a_name(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Undefined array key "name"');

        $permission = Permission::create(['guard_name' => 'web']);
    }

    public function test_a_permission_can_belong_to_a_role(): void
    {
        $permission = Permission::create(['name' => 'admin.panel']);

        $role = Role::create(['name' => 'Super Admin'])->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo('admin.panel'));
    }

    public function test_a_permission_can_belong_to_many_roles(): void
    {
        $permission = Permission::create(['name' => 'admin.panel']);

        $superAdmin = Role::create(['name' => 'Super Admin'])->givePermissionTo($permission);
        $admin = Role::create(['name' => 'Admin'])->givePermissionTo($permission);
        $editor = Role::create(['name' => 'Editor'])->givePermissionTo($permission);

        $this->assertTrue($superAdmin->hasPermissionTo('admin.panel'));
        $this->assertTrue($admin->hasPermissionTo('admin.panel'));
        $this->assertTrue($editor->hasPermissionTo('admin.panel'));

        $this->assertCount(3, $permission->roles);
    }
}
