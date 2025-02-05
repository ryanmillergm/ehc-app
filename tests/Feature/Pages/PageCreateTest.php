<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class PageTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test Pages database has correct columns
     */
    public function test_pages_database_has_expected_columns()
    {
        $this->assertTrue(
          Schema::hasColumns('pages', [
            'id', 'title', 'is_active',
        ]), 1);
    }

    /**
     * A Page can be created test
     */
    public function test_a_page_can_be_created_by_super_admin(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $attributes = [
            'title'         => 'Blog Test Example',
            'is_active'     => true,
        ];

        $response = $this->post('/pages', $attributes);

        $this->assertDatabaseHas('pages', $attributes);
    }


    /**
     * A Page can be created test
     */
    public function test_a_user_without_permissions_cannot_create_a_page_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $attributes = [
            'title'         => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');

        $response = $this->post('/pages', $attributes);
    }
}
