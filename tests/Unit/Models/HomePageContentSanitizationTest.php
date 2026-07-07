<?php

namespace Tests\Unit\Models;

use App\Models\HomePageContent;
use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_content_sanitizes_primary_rich_text_fields_on_save(): void
    {
        $content = HomePageContent::factory()->create([
            'language_id' => Language::factory()->english(),
            'hero_intro' => '<script>alert(1)</script><span class="text-lg">Intro</span>',
            'meeting_schedule' => '<a href="javascript:alert(1)" class="bad">Bad</a><a href="https://example.org" class="underline">Good</a>',
            'meeting_location' => '<p class="font-semibold">Township 9</p><script>x()</script>',
        ])->refresh();

        $this->assertStringNotContainsString('<script', (string) $content->hero_intro);
        $this->assertStringContainsString('class="text-lg"', (string) $content->hero_intro);

        $this->assertStringNotContainsString('javascript:', (string) $content->meeting_schedule);
        $this->assertStringContainsString('https://example.org', (string) $content->meeting_schedule);
        $this->assertStringContainsString('class="underline"', (string) $content->meeting_schedule);

        $this->assertStringNotContainsString('<script', (string) $content->meeting_location);
        $this->assertStringContainsString('class="font-semibold"', (string) $content->meeting_location);
    }
}
