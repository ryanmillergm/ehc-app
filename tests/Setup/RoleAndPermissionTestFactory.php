<?php

namespace Tests\Setup;

use App\Application;
use App\Models\Role;
use App\Models\User;
use Facades\Tests\Setup\EventTestFactory;

class RoleAndPermissionTestFactory
{
    protected $user;

    public function createSuperAdmin()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Super Admin']);

        $user->assignRole('Super Admin');

        return $user;
    }
}
