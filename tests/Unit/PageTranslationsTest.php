<?php

namespace Tests\Unit;

use App\Models\Language;
use App\Models\Image;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageTranslationsTest extends TestCase
{
    use RefreshDatabase;
    
    
    public function test_a_page_translation_belongs_to_a_page()
    {
        $translation = PageTranslation::factory()->create();

        $this->assertInstanceOf(Page::class, $translation->page);
    }

    public function test_a_page_translation_belongs_to_a_language()
    {
        $translation = PageTranslation::factory()->create();

        $this->assertInstanceOf(Language::class, $translation->language);
    }

    public function test_primary_rich_text_fields_are_sanitized_on_save(): void
    {
        $translation = PageTranslation::factory()->create([
            'title' => '<script>alert(1)</script><span class="text-rose-700">Title</span>',
            'description' => '<a href="javascript:alert(1)" class="bad">Bad</a><a href="https://example.org" class="underline">Good</a>',
            'content' => '<p class="lead">Body</p><script>evil()</script>',
            'hero_title' => '<span class="font-bold">Hero</span>',
            'hero_subtitle' => '<script>alert(2)</script><em class="italic">Sub</em>',
            'hero_cta_text' => '<span class="tracking-wide">Act</span>',
        ])->refresh();

        $this->assertStringNotContainsString('<script', (string) $translation->title);
        $this->assertStringContainsString('class="text-rose-700"', (string) $translation->title);

        $this->assertStringNotContainsString('javascript:', (string) $translation->description);
        $this->assertStringContainsString('https://example.org', (string) $translation->description);
        $this->assertStringContainsString('class="underline"', (string) $translation->description);

        $this->assertStringNotContainsString('<script', (string) $translation->content);
        $this->assertStringContainsString('<p class="lead">Body</p>', (string) $translation->content);
        $this->assertStringContainsString('class="font-bold"', (string) $translation->hero_title);
        $this->assertStringContainsString('class="italic"', (string) $translation->hero_subtitle);
        $this->assertStringContainsString('class="tracking-wide"', (string) $translation->hero_cta_text);
    }

    public function test_content_blocks_are_sanitized_recursively_on_save(): void
    {
        $translation = PageTranslation::factory()->create([
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'cta',
                    'data' => [
                        'title' => '<script>x()</script><span class="uppercase">Help now</span>',
                        'body' => '<a href="javascript:alert(1)" class="bad">Bad</a><a href="https://example.org" class="underline">Good</a>',
                    ],
                ],
                [
                    'type' => 'pricing',
                    'data' => [
                        'items' => [
                            [
                                'name' => '<span class="font-bold">Monthly</span><script>bad()</script>',
                                'features' => [
                                    [
                                        'text' => '<a href="javascript:alert(1)">Bad feature</a><span class="italic">Good feature</span>',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])->refresh();

        $block = $translation->content_blocks[0] ?? [];
        $this->assertStringNotContainsString('<script', (string) ($block['data']['title'] ?? ''));
        $this->assertStringContainsString('class="uppercase"', (string) ($block['data']['title'] ?? ''));
        $this->assertStringNotContainsString('javascript:', (string) ($block['data']['body'] ?? ''));
        $this->assertStringContainsString('https://example.org', (string) ($block['data']['body'] ?? ''));

        $pricing = $translation->content_blocks[1] ?? [];
        $feature = $pricing['data']['items'][0]['features'][0]['text'] ?? '';
        $this->assertStringNotContainsString('<script', (string) ($pricing['data']['items'][0]['name'] ?? ''));
        $this->assertStringNotContainsString('javascript:', (string) $feature);
        $this->assertStringContainsString('class="italic"', (string) $feature);
    }

    public function test_gallery_upload_content_block_creates_image_record_on_save(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cms/images/2026/07/gallery-upload.jpg', 'fake image bytes');

        $user = User::factory()->create();
        $this->actingAs($user);

        $translation = PageTranslation::factory()->create([
            'render_mode' => 'blocks',
            'content_blocks' => [
                [
                    'type' => 'gallery',
                    'data' => [
                        'items' => [
                            [
                                'source_type' => 'upload',
                                'upload_path' => 'cms/images/2026/07/gallery-upload.jpg',
                                'title' => 'Uploaded Gallery Image',
                                'alt' => 'Uploaded alt text',
                                'caption' => 'Uploaded caption',
                            ],
                        ],
                    ],
                ],
            ],
        ])->refresh();

        $image = Image::query()
            ->where('disk', 'public')
            ->where('path', 'cms/images/2026/07/gallery-upload.jpg')
            ->firstOrFail();

        $item = $translation->content_blocks[0]['data']['items'][0] ?? [];

        $this->assertSame($image->id, $item['image_id'] ?? null);
        $this->assertSame('existing', $item['source_type'] ?? null);
        $this->assertArrayNotHasKey('upload_path', $item);
        $this->assertSame(url('/storage/cms/images/2026/07/gallery-upload.jpg'), $image->public_url);
        $this->assertSame('Uploaded Gallery Image', $image->title);
        $this->assertSame('Uploaded alt text', $image->alt_text);
        $this->assertSame('Uploaded caption', $image->caption);
        $this->assertSame($user->id, $image->created_by);
    }

    public function test_custom_html_is_sanitized_with_scripts_blocked_even_when_trusted(): void
    {
        $translation = PageTranslation::factory()->create([
            'render_mode' => 'custom',
            'custom_html_is_trusted' => true,
            'custom_html' => '<div onclick="alert(1)"><script>alert(2)</script><iframe src="https://www.youtube.com/embed/abc123"></iframe></div>',
        ])->refresh();

        $this->assertStringNotContainsString('<script', (string) $translation->custom_html);
        $this->assertStringNotContainsString('onclick=', (string) $translation->custom_html);
        $this->assertStringContainsString('<iframe', (string) $translation->custom_html);
    }

    public function test_custom_html_trusted_flag_is_forced_off_without_permission_for_authenticated_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $translation = PageTranslation::factory()->create([
            'render_mode' => 'custom',
            'custom_html_is_trusted' => true,
            'custom_html' => '<div>Test</div>',
        ])->refresh();

        $this->assertFalse((bool) $translation->custom_html_is_trusted);
    }

    public function test_invalid_hero_fields_are_normalized_to_defaults(): void
    {
        $translation = PageTranslation::factory()->create([
            'hero_style' => 'bad-style',
            'hero_height' => '999',
            'hero_overlay' => 'bad-overlay',
            'hero_text_align' => 'bad-align',
            'hero_text_width' => 'bad-width',
        ])->refresh();

        $this->assertSame('contained', $translation->hero_style);
        $this->assertSame('80', $translation->hero_height);
        $this->assertSame('medium', $translation->hero_overlay);
        $this->assertSame('left', $translation->hero_text_align);
        $this->assertSame('normal', $translation->hero_text_width);
    }

    public function test_valid_hero_fields_are_preserved(): void
    {
        $translation = PageTranslation::factory()->create([
            'hero_style' => 'full_bleed',
            'hero_height' => '100',
            'hero_overlay' => 'dark',
            'hero_text_align' => 'center',
            'hero_text_width' => 'wide',
        ])->refresh();

        $this->assertSame('full_bleed', $translation->hero_style);
        $this->assertSame('100', $translation->hero_height);
        $this->assertSame('dark', $translation->hero_overlay);
        $this->assertSame('center', $translation->hero_text_align);
        $this->assertSame('wide', $translation->hero_text_width);
    }
}
