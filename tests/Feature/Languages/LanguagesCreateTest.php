<?php

namespace Tests\Feature\Languages;

use App\Models\Language;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LanguagesCreateTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * A language cannot be created without permissions test
     */
    public function test_a_user_without_permissions_cannot_create_a_language_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $attributes = [
            'title'         => 'english',
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
            'title'         => 'english',
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
    public function test_a_user_with_permissions_can_create_a_language_instance(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'languages.create')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'title'         => 'english',
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ];

        $response = $this->post('/languages', $attributes);

        $response->assertOk();

        $this->assertDatabaseHas('languages', $attributes);
    }


    /**
     * A language requires a title test
     */
    public function test_a_language_requires_a_title(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'languages.create')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'iso_code'      => 'en',
            'locale'        => 'en',
            'right_to_left' => false,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The title field is required.');

        $response = $this->post('/languages', $attributes);
    }


    /**
     * A language requires an iso_code test
     */
    public function test_a_language_requires_a_iso_code(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'languages.create')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'title'         => 'english',
            'locale'        => 'en',
            'right_to_left' => false,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The iso code field is required.');

        $response = $this->post('/languages', $attributes);
    }


    /**
     * A language requires an locale test
     */
    public function test_a_language_requires_a_locale(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'languages.create')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'title'         => 'english',
            'iso_code'      => 'en',
            'right_to_left' => false,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The locale field is required.');

        $response = $this->post('/languages', $attributes);
    }


    /**
     * A language right to left field should default to false test
     */
    public function test_a_language_right_to_left_should_default_to_false(): void
    {
        $this->withoutExceptionHandling();

        $user = $this->signIn();

        $permission = Permission::where('name', 'languages.create')->first();

        $user->givePermissionTo($permission->id);

        $attributes = [
            'title'         => 'english',
            'iso_code'      => 'en',
            'locale'        => 'en',
        ];

        $response = $this->post('/languages', $attributes);

        $response->assertOk();

        $this->assertDatabaseHas('languages', $attributes);

        $language = Language::where($attributes)->first();

        $this->assertEquals($language->right_to_left, false);
    }


}
