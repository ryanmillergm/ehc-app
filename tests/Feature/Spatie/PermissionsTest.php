<?php

namespace Tests\Feature\Spatie;

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
        $permission = Permission::create(['name' => '', 'guard_name' => 'web']);
        // $permission = Permission::create(['name' => 'edit articles']);

        $this->assertDatabaseCount('permissions', 0);
    }
}
