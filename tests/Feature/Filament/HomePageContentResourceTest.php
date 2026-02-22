<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\HomePageContents\HomePageContentResource;
use App\Filament\Resources\HomePageContents\Pages\CreateHomePageContent;
use App\Filament\Resources\HomePageContents\Pages\EditHomePageContent;
use App\Models\HomePageContent;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_create_home_page_content_sanitizes_rich_fields(): void
    {
        $language = Language::factory()->english()->create();

        Livewire::test(CreateHomePageContent::class)
            ->fillForm([
                'language_id' => $language->id,
                'hero_intro' => '<script>alert(1)</script><span class="text-lg">Intro</span>',
                'meeting_schedule' => '<a href="javascript:alert(1)" class="bad">Bad</a><span class="font-semibold">Thursday</span>',
                'meeting_location' => '<span class="underline">Township 9</span>',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = HomePageContent::query()->where('language_id', $language->id)->firstOrFail();

        $this->assertStringNotContainsString('<script', (string) $record->hero_intro);
        $this->assertStringContainsString('class="text-lg"', (string) $record->hero_intro);
        $this->assertStringNotContainsString('javascript:', (string) $record->meeting_schedule);
        $this->assertStringContainsString('class="font-semibold"', (string) $record->meeting_schedule);
        $this->assertStringContainsString('class="underline"', (string) $record->meeting_location);
    }

    public function test_edit_home_page_content_sanitizes_rich_fields(): void
    {
        $record = HomePageContent::factory()->for(Language::factory()->english())->create();

        Livewire::test(EditHomePageContent::class, ['record' => $record->getKey()])
            ->fillForm([
                'hero_intro' => '<span class="text-rose-700">Updated intro</span><script>x()</script>',
                'meeting_schedule' => '<span class="tracking-wide">Updated schedule</span>',
                'meeting_location' => '<a href="javascript:alert(1)">Bad</a><a href="https://example.org" class="underline">Good</a>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();

        $this->assertStringNotContainsString('<script', (string) $record->hero_intro);
        $this->assertStringContainsString('class="text-rose-700"', (string) $record->hero_intro);
        $this->assertStringContainsString('class="tracking-wide"', (string) $record->meeting_schedule);
        $this->assertStringNotContainsString('javascript:', (string) $record->meeting_location);
        $this->assertStringContainsString('https://example.org', (string) $record->meeting_location);
    }
}
