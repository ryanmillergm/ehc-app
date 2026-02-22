<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\PageTranslationResource;
use App\Filament\Resources\PageTranslationResource\Pages\CreatePageTranslation;
use App\Filament\Resources\PageTranslationResource\Pages\EditPageTranslation;
use App\Filament\Resources\PageTranslationResource\Pages\ListPageTranslations;
use App\Filament\Resources\PageTranslationResource\Pages\ViewPageTranslation;
use App\Filament\Resources\PageTranslationResource\RelationManagers\SeoMetaRelationManager;
use App\Models\SeoMeta;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
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

    public function test_page_translation_index_shows_docs_header_action(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'admin.panel']);

        $this->get(PageTranslationResource::getUrl('index'))
            ->assertOk()
            ->assertSee('SEO Docs')
            ->assertSee(SeoDocumentation::getUrl());
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

    public function test_create_page_translation_sanitizes_rich_text_fields(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $page = Page::factory()->create();
        $language = Language::factory()->create();

        Livewire::test(CreatePageTranslation::class)
            ->fillForm([
                'page_id' => $page->id,
                'language_id' => $language->id,
                'title' => '<script>alert(1)</script><span class="text-rose-700">Rich title</span>',
                'slug' => 'sanitized-rich-title',
                'description' => '<a href="javascript:alert(1)">Bad</a><a href="https://example.org" class="underline">Good</a>',
                'content' => '<p class="lead">Body</p><script>x()</script>',
                'hero_title' => '<span class="font-bold">Hero</span>',
                'hero_subtitle' => '<em class="italic">Sub</em>',
                'hero_cta_text' => '<span class="tracking-wide">Act</span>',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $translation = PageTranslation::query()
            ->where('slug', 'sanitized-rich-title')
            ->firstOrFail();

        $this->assertStringNotContainsString('<script', (string) $translation->title);
        $this->assertStringContainsString('class="text-rose-700"', (string) $translation->title);
        $this->assertStringNotContainsString('javascript:', (string) $translation->description);
        $this->assertStringContainsString('https://example.org', (string) $translation->description);
        $this->assertStringContainsString('<p class="lead">Body</p>', (string) $translation->content);
        $this->assertStringContainsString('class="font-bold"', (string) $translation->hero_title);
        $this->assertStringContainsString('class="italic"', (string) $translation->hero_subtitle);
        $this->assertStringContainsString('class="tracking-wide"', (string) $translation->hero_cta_text);
    }

    public function test_edit_page_translation_sanitizes_rich_text_fields(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.create', 'pages.update', 'pages.delete', 'admin.panel']);

        $translation = PageTranslation::factory()->create([
            'title' => 'Plain title',
            'slug' => 'edit-sanitized-page-translation',
            'description' => 'Plain description',
            'content' => '<p>Plain body</p>',
            'is_active' => true,
        ]);

        Livewire::test(EditPageTranslation::class, ['record' => $translation->getKey()])
            ->fillForm([
                'title' => '<script>x()</script><span class="text-sky-700">Updated title</span>',
                'slug' => 'edit-sanitized-page-translation',
                'description' => '<a href="javascript:alert(1)">Bad</a><a href="https://example.org" class="underline">Good</a>',
                'content' => '<p class="lead">Updated body</p><script>x()</script>',
                'hero_title' => '<span class="font-bold">Updated hero</span>',
                'hero_subtitle' => '<em class="italic">Updated sub</em>',
                'hero_cta_text' => '<span class="tracking-wide">Updated CTA</span>',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $translation->refresh();

        $this->assertStringNotContainsString('<script', (string) $translation->title);
        $this->assertStringContainsString('class="text-sky-700"', (string) $translation->title);
        $this->assertStringNotContainsString('javascript:', (string) $translation->description);
        $this->assertStringContainsString('https://example.org', (string) $translation->description);
        $this->assertStringContainsString('<p class="lead">Updated body</p>', (string) $translation->content);
        $this->assertStringContainsString('class="font-bold"', (string) $translation->hero_title);
        $this->assertStringContainsString('class="italic"', (string) $translation->hero_subtitle);
        $this->assertStringContainsString('class="tracking-wide"', (string) $translation->hero_cta_text);
    }

    public function test_seo_meta_relation_manager_renders_for_page_translation(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])->assertSuccessful();
    }

    public function test_page_translation_view_page_renders_seo_meta_relation_manager_section(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();

        $this->get(PageTranslationResource::getUrl('view', ['record' => $translation]))
            ->assertOk()
            ->assertSee('SeoMetaRelationManager');
    }

    public function test_seo_meta_relation_manager_is_actionable_from_both_view_and_edit_contexts(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();
        $record = $translation->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->firstOrFail();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])
            ->callTableAction(EditAction::class, $record, [
                'seo_title' => 'Updated from view context',
            ])
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('Updated from view context', $record->seo_title);

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => EditPageTranslation::class,
        ])
            ->callTableAction(EditAction::class, $record, [
                'seo_title' => 'Updated from edit context',
            ])
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('Updated from edit context', $record->seo_title);
    }

    public function test_create_action_visibility_changes_based_on_canonical_row_presence(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])->assertDontSee('Create Canonical SEO');

        $translation->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->delete();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])->assertSee('Create Canonical SEO');
    }

    public function test_seo_meta_relation_manager_edits_and_deletes_existing_canonical_row(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();

        $record = SeoMeta::query()
            ->where('seoable_type', $translation->getMorphClass())
            ->where('seoable_id', $translation->id)
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->firstOrFail();

        $this->assertSame('', $record->target_key);
        $this->assertSame($translation->language_id, $record->language_id);
        $this->assertNull($record->seo_title);

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])
            ->callTableAction(EditAction::class, $record, [
                'seo_title' => 'Updated Canonical SEO Title',
            ])
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('Updated Canonical SEO Title', $record->seo_title);

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])->callTableAction(DeleteAction::class, $record);

        $this->assertDatabaseMissing('seo_meta', [
            'id' => $record->id,
        ]);
    }

    public function test_seo_meta_relation_manager_can_create_canonical_row_when_missing(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();
        $otherLanguage = Language::factory()->create();

        $translation->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->delete();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'target_key' => 'evil-key',
                    'language_id' => $otherLanguage->id,
                    'seo_title' => 'Canonical SEO Title',
                    'seo_description' => 'Canonical SEO Description',
                    'seo_og_image' => 'https://cdn.example.org/canonical-og.jpg',
                    'is_active' => true,
                ]
            )
            ->assertHasNoFormErrors();

        $record = SeoMeta::query()
            ->where('seoable_type', $translation->getMorphClass())
            ->where('seoable_id', $translation->id)
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->firstOrFail();

        $this->assertSame('Canonical SEO Title', $record->seo_title);
    }

    public function test_seo_meta_relation_manager_forces_canonical_target_and_owner_language_on_edit(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();
        $otherLanguage = Language::factory()->create();

        $record = $translation->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->firstOrFail();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])
            ->callTableAction(EditAction::class, $record, [
                'target_key' => 'attempted-non-canonical',
                'language_id' => $otherLanguage->id,
                'seo_title' => 'Forced canonical edit',
            ])
            ->assertHasNoFormErrors();

        $record->refresh();
        $this->assertSame('', $record->target_key);
        $this->assertSame($translation->language_id, $record->language_id);
        $this->assertSame('Forced canonical edit', $record->seo_title);
    }

    public function test_seo_meta_relation_manager_delete_then_recreate_flow_works(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();
        $record = $translation->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->firstOrFail();

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])->callTableAction(DeleteAction::class, $record);

        $this->assertDatabaseMissing('seo_meta', [
            'id' => $record->id,
        ]);

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])
            ->callAction(
                TestAction::make(CreateAction::class)->table(),
                [
                    'seo_title' => 'Recreated Canonical SEO',
                    'seo_description' => 'Recreated description',
                    'seo_og_image' => 'https://cdn.example.org/recreated-og.jpg',
                    'is_active' => true,
                ]
            )
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('seo_meta', [
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => '',
            'language_id' => $translation->language_id,
            'seo_title' => 'Recreated Canonical SEO',
        ]);
    }

    public function test_seo_meta_relation_manager_only_lists_canonical_row_for_translation_language(): void
    {
        $this->signInWithPermissions(null, ['pages.read', 'pages.update', 'admin.panel']);

        $translation = PageTranslation::factory()->create();

        $translation->seoMeta()
            ->where('target_key', '')
            ->where('language_id', $translation->language_id)
            ->update([
                'seo_title' => 'Canonical Visible Row',
                'is_active' => true,
            ]);

        SeoMeta::query()->create([
            'seoable_type' => $translation->getMorphClass(),
            'seoable_id' => $translation->id,
            'target_key' => 'hero',
            'language_id' => $translation->language_id,
            'seo_title' => 'Non-Canonical Hidden Row',
            'is_active' => true,
        ]);

        Livewire::test(SeoMetaRelationManager::class, [
            'ownerRecord' => $translation,
            'pageClass' => ViewPageTranslation::class,
        ])
            ->assertSee('Canonical Visible Row')
            ->assertDontSee('Non-Canonical Hidden Row');
    }
}
