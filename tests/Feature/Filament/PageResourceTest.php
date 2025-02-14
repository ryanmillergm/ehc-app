<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\PageResource\RelationManagers\PageTranslationsRelationManager;
use App\Filament\Resources\PageTranslationResource\Pages\EditPageTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Livewire\Livewire;

class PageResourceTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->seed('PageSeeder');
    }

    /**
     * An authenticated user with Super Admin role can visit the Page resource in the filament admin panel.
     */
    public function test_an_authenticated_user_with_super_admin_role_can_visit_admin_page_and_view_page_resource(): void
    {
        $user = User::factory()->create();
        $this->signInAsSuperAdmin($user);

        $response = $this->get('/admin');

        $response->assertStatus(200);

        $this->get(PageResource::getUrl('index'))->assertSuccessful();
    }


    /**
     * Test an authenticated user with permissions can visit the Page resource page in the filament admin panel.
     */
    public function test_an_authenticated_user_with_permissions_can_render_the_page_resource_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'admin.panel']);

        $this->get(PageResource::getUrl('index'))->assertSuccessful();
    }

    /**
     * Test an authenticated user w/out permissions cannot render the Page resource in the filament admin panel.
     */
    public function test_an_authenticated_user_without_permissions_cannot_render_the_page_resource_page(): void
    {
        $this->get(PageResource::getUrl('index'))->assertRedirect('admin/login');

        $this->signInWithPermissions(null, ['teams.read', 'admin.panel']);

        $this->get(PageResource::getUrl('index'))->assertStatus(403);
    }

    /**
     * Test an authenticated user with permissions can visit the page resource table builder list page and see a list of pages.
     */
    public function test_page_resource_page_can_list_pages(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);
        $num = 7;
        $count = Page::all()->count() + $num;
        Page::factory()->count($num)->create();
        $pages = Page::all();

        livewire::test(ListPages::class)
        ->assertCountTableRecords($count)
        ->assertCanSeeTableRecords($pages);
    }

    /**
     * Test an authenticated user with permissions can visit the create a page resource page
     */
    public function test_auth_page_visit_create_page_resource_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $this->get(PageResource::getUrl('create'))->assertSuccessful();
    }

    /**
     * Test an authenticated user with permissions can create a page resource
     */
    public function test_auth_user_can_create_page(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $newData = Page::factory()->make();

        livewire::test(CreatePage::class)
            ->fillForm([
                'title' => $newData->title,
                'is_active' => $newData->is_active,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Page::class, [
            'title' => $newData->title,
            'is_active' => $newData->is_active,
        ]);
    }

    /**
     * Test validation - Page requires title
     */
    public function test_create_page_requires_title(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $newData = Page::factory()->make();

        livewire::test(CreatePage::class)
            ->fillForm([
                'title' => null,
                'is_active' => $newData->is_active,
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);

        $this->assertDatabaseMissing(Page::class, [
            'title' => $newData->title,
            'is_active' => $newData->is_active,
        ]);
    }

    /**
     * Test Page resource renders relation manager successfully
     */
    public function test_page_resource_renders_relation_manager_successfully(): void
    {
        $page = Page::factory()
            ->has(PageTranslation::factory()->count(1), 'pageTranslations')
            ->create();

        livewire::test(PageTranslationsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditPageTranslation::class,
        ])
            ->assertSuccessful();
    }

    /**
     * Test Page resource renders relation manager successfully
     */
    public function test_page_resource_lists_page_translations(): void
    {
        $page = Page::factory()
            ->has(PageTranslation::factory()->count(3), 'pageTranslations')
            ->create();

        livewire::test(PageTranslationsRelationManager::class, [
            'ownerRecord' => $page,
            'pageClass' => EditPageTranslation::class,
        ])
            ->assertCanSeeTableRecords($page->pageTranslations);
    }
}
