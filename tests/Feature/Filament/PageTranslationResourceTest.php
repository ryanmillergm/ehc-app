<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageTranslationResource;
use App\Filament\Resources\PageTranslationResource\Pages\ListPageTranslations;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;

class PageTranslationResourceTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
    }

    /**
     * Test an authenticated user can visit the Page Translation resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_without_permissions_cannot_render_the_page_translation_resource_page(): void
    {
        $this->get(PageTranslationResource::getUrl('index'))->assertRedirect('admin/login');

        $this->signInWithPermissions(null, ['language.read', 'admin.panel']);

        $this->get(PageTranslationResource::getUrl('index'))->assertStatus(403);
    }

    /**
     * Test an authenticated user with permissions can visit the Page Translations resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_page_translation_resource_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'admin.panel']);

        $this->get(PageTranslationResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * Test an authenticated user with permissions can visit the Page Translation resource table builder list page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_page_translation_resource_table_list_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        Livewire::test(ListPageTranslations::class)->assertSuccessful();
    }

    /**
     * Test an authenticated user with permissions can visit the Page Translation resource table builder list page and see a list of page translations.
     */
    public function test_page_translation_resource_page_can_list_page_translations(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);
        $num = 7;
        $count = PageTranslation::all()->count() + $num;
        PageTranslation::factory()->count($num)->create();
        $translations = PageTranslation::all();

        livewire::test(ListPageTranslations::class)
        ->assertCountTableRecords($count)
        ->assertCanSeeTableRecords($translations);
    }

    /**
     * Test an authenticated user with permissions can visit the create a Page Translation resource page
     */
    public function test_auth_user_can_visit_create_page_translation_resource_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $this->get(PageTranslationResource::getUrl('create'))->assertSuccessful();
    }
}

