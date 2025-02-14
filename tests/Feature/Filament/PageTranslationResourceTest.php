<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PageTranslationResource;
use App\Filament\Resources\PageTranslationResource\Pages\CreatePageTranslation;
use App\Filament\Resources\PageTranslationResource\Pages\ListPageTranslations;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Support\Str;

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

    /**
     * Test an authenticated user with permissions can create a page resource
     */
    public function test_auth_user_can_create_a_page_translation(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $newData = PageTranslation::factory()->make();
        $page = Page::factory()->create();
        $language = Language::factory()->create();

        livewire::test(CreatePageTranslation::class)
            ->fillForm([
                'page_id'       => $page->id,
                'language_id'   => $language->id,
                'title'         => $newData->title,
                'slug'          => 'test-create-page-translation',
                'description'   => $newData->description,
                'content'       => $newData->content,
                'is_active'     => $newData->is_active,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(PageTranslation::class, [
            'title'         => $newData->title,
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'slug'          => 'test-create-page-translation',
            'description'   => $newData->description,
            'content'       => $newData->content,
            'is_active'     => $newData->is_active,
        ]);
    }

    /**
     * Test a slug is automatically generated from title when creating a page translation
     */
    public function test_a_slug_is_automatically_generated_from_title_when_creating_a_page_translation(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $newData = PageTranslation::factory()->make();
        $page = Page::factory()->create();
        $language = Language::factory()->create();

        $slug = Str::slug($newData->title, '-');

        livewire::test(CreatePageTranslation::class)
            ->fillForm([
                'page_id'       => $page->id,
                'language_id'   => $language->id,
                'title'         => $newData->title,
                'description'   => $newData->description,
                'content'       => $newData->content,
                'is_active'     => $newData->is_active,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(PageTranslation::class, [
            'title'         => $newData->title,
            'page_id'       => $page->id,
            'language_id'   => $language->id,
            'slug'          => $slug,
            'description'   => $newData->description,
            'content'       => $newData->content,
            'is_active'     => $newData->is_active,
        ]);
    }
}

