<?php

namespace Tests\Feature\Languages;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class LanguagesTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test langugages database has correct columns
     */
    public function test_langugages_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('languages', [
            'title', 'iso_code', 'locale', 'right_to_left', 'created_at'
        ]), 1);
    }

    /**
     * A language cannot be created without permissions test
     */
    public function test_a_user_without_permissions_cannot_create_a_language_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $attributes = [
            'title'         => 'English',
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ];

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');

        $response = $this->post('/languages', $attributes);
    }

    /**
     * A language can be created by super admin test
     */
    public function test_a_language_can_be_created_by_super_admin(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $attributes = [
            'title'         => 'English',
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ];

        $response = $this->post('/languages', $attributes);

        $this->assertDatabaseHas('languages', $attributes);
    }


    /**
     * A child can be created test
     */
    public function test_a_user_with_permissions_can_create_a_child_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'languages.create')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'title'         => 'English',
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ];

        $response = $this->post('/languages', $attributes);

        $response->assertOk();

        $this->assertDatabaseHas('languages', $attributes);
    }
}
