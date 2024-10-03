<?php

namespace Tests\Feature\Filament;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Livewire\Livewire;

class LanguageResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->seed('LanguageSeeder');

        $this->signInWithPermissions(null, ['languages.read', 'languages.create', 'languages.update', 'languages.delete', 'admin.panel']);
    }

    /**
     * Test an authenticated user with permissions can visit the language resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_language_resource_page(): void
    {
        $this->signInWithPermissions(null, ['languages.read', 'admin.panel']);

        $this->get(LanguageResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * Test an authenticated user with permissions can visit the language resource table builder list page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_language_resource_table_page(): void
    {
        $this->signInWithPermissions(null, ['language.read', 'language.create', 'language.update', 'language.delete', 'admin.panel']);

        livewire::test(ListLanguages::class)->assertSuccessful();
    }

    /**
     * Test an authenticated userwith permissions can visit the language resource table builder list page and see a list of users.
     */
    public function test_language_resource_page_can_list_languages(): void
    {
        $this->signInWithPermissions(null, ['languages.read', 'languages.create', 'languages.update', 'languages.delete', 'admin.panel']);
        $count = count(Language::all() + 7);
        language::factory()->count(7)->create();
        $languages = language::all();

        livewire::test(ListLanguages::class)
        ->assertCountTableRecords($count)
        ->assertCanSeeTableRecords($languages);
    }

    /**
     * Test an authenticated user with permissions can visit the create a language resource page
     */
    public function test_auth_language_visit_create_language_resource_page(): void
    {
        $this->signInWithPermissions(null, ['languages.read', 'languages.create', 'languages.update', 'languages.delete', 'admin.panel']);

        $this->get(LanguageResource::getUrl('create'))->assertSuccessful();
    }
}
