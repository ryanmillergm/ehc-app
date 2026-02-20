<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\RouteSeoResource;
use App\Filament\Resources\RouteSeoResource\Pages\CreateRouteSeo;
use App\Models\Language;
use App\Support\Seo\RouteSeoTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RouteSeoResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_route_seo_index_shows_docs_header_action(): void
    {
        $this->get(RouteSeoResource::getUrl('index'))
            ->assertOk()
            ->assertSee('SEO Docs')
            ->assertSee(SeoDocumentation::getUrl());
    }

    public function test_route_seo_create_persists_unified_target_columns(): void
    {
        $language = Language::factory()->english()->create();

        Livewire::test(CreateRouteSeo::class)
            ->fillForm([
                'target_key' => RouteSeoTarget::DONATIONS_SHOW,
                'language_id' => $language->id,
                'seo_title' => 'Give SEO Title',
                'seo_description' => 'Give SEO Description',
                'seo_og_image' => 'https://cdn.example.org/give-og.jpg',
                'canonical_path' => '/give',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('seo_meta', [
            'seoable_type' => 'route',
            'seoable_id' => 0,
            'target_key' => RouteSeoTarget::DONATIONS_SHOW,
            'language_id' => $language->id,
            'seo_title' => 'Give SEO Title',
            'seo_description' => 'Give SEO Description',
            'seo_og_image' => 'https://cdn.example.org/give-og.jpg',
        ]);
    }
}
