<?php

namespace Tests\Feature\Children;

use App\Models\Child;
use App\Models\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class ChildTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test children database has correct columns
     */
    public function test_children_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('children', [
            'first_name', 'last_name', 'date_of_birth', 'country', 'city', 'description', 'team_id', 'created_at'
        ]), 1);
    }

    /**
     * A child can be created test
     */
    public function test_a_user_without_permissions_cannot_create_a_child_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $attributes = [
            'first_name'    => 'Paul',
            'last_name'     => 'Smith',
            'date_of_birth' => $this->faker->date('Y-m-d'),
            'country'       => $this->faker->country(),
            'city'          => $this->faker->city(),
            'description'   => $this->faker->paragraph(1),
            'team_id'       => $this->faker->numberBetween(1, 3),
        ];

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');

        $response = $this->post('/children', $attributes);
    }

    /**
     * A child can be created test
     */
    public function test_a_child_can_be_created_by_super_admin(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $attributes = [
            'first_name' => 'Paul',
            'last_name' => 'Smith',
            'date_of_birth' => '1995-02-15',
            'country' => 'USA',
            'city' => 'Burbank',
            'description' => 'lost child from a long time ago',
            'team_id' => 3
        ];

        $response = $this->post('/children', $attributes);

        $this->assertDatabaseHas('children', $attributes);
    }


    /**
     * A child can be created test
     */
    public function test_a_user_with_permissions_can_create_a_child_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'children.write')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'first_name' => 'John',
            'last_name' => 'Paul',
            'date_of_birth' => '1995-02-15',
            'country' => 'USA',
            'city' => 'Burbank',
            'description' => 'lost child from a long time ago',
            'team_id' => $this->faker->numberBetween(1, 3),
        ];

        $response = $this->post('/children', $attributes);

        $response->assertOk();

        $this->assertDatabaseHas('children', $attributes);
    }
}
