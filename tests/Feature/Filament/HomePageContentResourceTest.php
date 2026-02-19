<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\HomePageContents\HomePageContentResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageContentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed('PermissionSeeder');
        $this->signInWithPermissions(null, ['admin.panel']);
    }

    public function test_home_page_content_index_shows_docs_header_action(): void
    {
        $this->get(HomePageContentResource::getUrl('index'))
            ->assertOk()
            ->assertSee('SEO Docs')
            ->assertSee(SeoDocumentation::getUrl());
    }
}
