<?php

namespace Tests\Feature\PageTranslations;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PageTranslationsCreateTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * Test PageTranslations database has correct columns
     */
    public function test_page_tranlations_database_has_expected_columns()
    {
        $this->withoutExceptionHandling();

        $this->assertTrue(
          Schema::hasColumns('page_translations', [
            'id', 'page_id', 'language_id', 'title', 'slug', 'description', 'content', 'is_active'
        ]), 1);
    }

    /**
     * A Page translation can be created by a super admin test
     */
    public function test_a_page_translation_can_be_created_by_super_admin(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();
        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseHas('page_translations', $attributes);
    }

    /**
     * A Page Translation can't be created by user without permissions test
     */
    public function test_a_user_without_permissions_cannot_create_a_page_translation_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $page = Page::factory()->create();
        $language = Language::where('title', 'English')->get()->first();


        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This action is unauthorized.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }

    /**
     * A Page Translation can be created by user with proper permissions test
     */
    public function test_a_user_with_permissions_can_create_a_page_translation_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'pages.create')->first();

        $user->givePermissionTo($permission->id);

        $page = Page::factory()->create();

        $language = Language::where('title', 'English')->get()->first();


        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $response->assertOk();

        $this->assertDatabaseHas('page_translations', $attributes);
    }

    /**
     * A Page Translation requires a language test
     */
    public function test_a_page_translation_requires_a_language(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();

        $attributes = [
            'page_id'       => $page->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The language id field is required.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }

    /**
     * A Page Translation requires a title test
     */
    public function test_a_page_translation_requires_a_title(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();

        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'slug'          => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The title field is required.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }

    /**
     * A Page Translation requires a slug test
     */
    public function test_a_page_translation_requires_a_slug(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();

        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The slug field is required.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }

    /**
     * A Page Translation requires a description test
     */
    public function test_a_page_translation_requires_a_description(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();

        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'blog-test-example',
            'content'       => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The description field is required.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }

    /**
     * A Page Translation requires a content test
     */
    public function test_a_page_translation_requires_a_content(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();

        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'blog-test-example',
            'description'   => 'Blog Test Example',
            'is_active'     => true,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The content field is required.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }

    /**
     * A Page Translation does not require is_active test defaults as false
     */
    public function test_a_page_translation_is_active_is_required(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signInAsSuperAdmin();

        $page = Page::factory()->create();

        $language = Language::where('title', 'English')->get()->first();

        $attributes = [
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'title'         => 'Blog Test Example',
            'slug'          => 'blog-test-example',
            'description'   => 'Blog Test Example',
            'content'       => 'Blog Test Example',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The is active field is required.');

        $response = $this->post('/pages/' . $page->id . '/translations', $attributes);

        $this->assertEquals(false, $response->is_active);

        $this->assertDatabaseMissing('page_translations', $attributes);
    }
}
