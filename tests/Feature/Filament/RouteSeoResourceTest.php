<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\RouteSeoResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
