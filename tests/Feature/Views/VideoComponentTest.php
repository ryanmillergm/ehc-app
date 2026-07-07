<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoComponentTest extends TestCase
{
    #[Test]
    public function it_renders_allowed_embed_sources_with_iframe(): void
    {
        $this->blade('<x-media.video :video="$video" />', [
            'video' => [
                'url' => 'https://www.youtube.com/embed/abc123',
                'source_type' => 'embed',
                'title' => 'YouTube Embed',
            ],
        ])
            ->assertSee('<iframe', false)
            ->assertSee('youtube.com/embed/abc123')
            ->assertDontSee('data-hero-background-video', false)
            ->assertDontSee('pointer-events-none', false);
    }

    #[Test]
    public function it_does_not_render_iframe_for_disallowed_embed_host(): void
    {
        $this->blade('<x-media.video :video="$video" />', [
            'video' => [
                'url' => 'https://evil.example.com/embed/abc123',
                'source_type' => 'embed',
                'title' => 'Bad Host',
            ],
        ])
            ->assertDontSee('<iframe', false);
    }

    #[Test]
    public function it_renders_uploaded_video_with_expected_attributes(): void
    {
        $this->blade('<x-media.video :video="$video" variant="hero" :autoplay="true" :muted="true" :loop="true" :controls="false" />', [
            'video' => [
                'url' => 'https://cdn.example.org/video.mp4',
                'source_type' => 'upload',
                'poster_url' => 'https://cdn.example.org/poster.jpg',
                'title' => 'Upload Video',
            ],
        ])
            ->assertSee('<video', false)
            ->assertSee('src="https://cdn.example.org/video.mp4"', false)
            ->assertSee('poster="https://cdn.example.org/poster.jpg"', false)
            ->assertSee('autoplay', false)
            ->assertSee('muted', false)
            ->assertSee('loop', false)
            ->assertDontSee('controls', false);
    }

    #[Test]
    public function it_defaults_uploaded_full_bleed_hero_video_to_background_playback(): void
    {
        $this->blade('<x-media.video :video="$video" variant="hero" layout="full_bleed" />', [
            'video' => [
                'url' => 'https://cdn.example.org/hero.mp4',
                'source_type' => 'upload',
                'poster_url' => 'https://cdn.example.org/poster.jpg',
                'title' => 'Hero Upload',
            ],
        ])
            ->assertSee('data-hero-background-video="true"', false)
            ->assertSee('<video', false)
            ->assertSee('autoplay', false)
            ->assertSee('muted', false)
            ->assertSee('loop', false)
            ->assertSee('preload="auto"', false)
            ->assertSee('object-cover', false)
            ->assertDontSee('controls', false);
    }

    #[Test]
    public function it_renders_youtube_full_bleed_hero_embed_as_background_media(): void
    {
        $this->blade('<x-media.video :video="$video" variant="hero" layout="full_bleed" />', [
            'video' => [
                'url' => 'https://www.youtube.com/embed/abc123?controls=1',
                'source_type' => 'embed',
                'title' => 'Hero YouTube',
            ],
        ])
            ->assertSee('data-hero-background-video="true"', false)
            ->assertSee('<iframe', false)
            ->assertSee('pointer-events-none', false)
            ->assertSee('loading="eager"', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('autoplay=1', false)
            ->assertSee('mute=1', false)
            ->assertSee('controls=0', false)
            ->assertSee('loop=1', false)
            ->assertSee('playlist=abc123', false)
            ->assertSee('modestbranding=1', false)
            ->assertSee('iv_load_policy=3', false)
            ->assertSee('disablekb=1', false)
            ->assertSee('fs=0', false)
            ->assertDontSee('allowfullscreen', false);
    }

    #[Test]
    public function it_renders_vimeo_full_bleed_hero_embed_as_background_media(): void
    {
        $this->blade('<x-media.video :video="$video" variant="hero" layout="full_bleed" />', [
            'video' => [
                'url' => 'https://player.vimeo.com/video/123456',
                'source_type' => 'embed',
                'title' => 'Hero Vimeo',
            ],
        ])
            ->assertSee('data-hero-background-video="true"', false)
            ->assertSee('background=1', false)
            ->assertSee('autoplay=1', false)
            ->assertSee('muted=1', false)
            ->assertSee('loop=1', false)
            ->assertSee('controls=0', false);
    }

    #[Test]
    public function it_escapes_attribute_content_in_embed_src(): void
    {
        $this->blade('<x-media.video :video="$video" />', [
            'video' => [
                'url' => 'https://www.youtube.com/embed/abc123" onload="alert(1)',
                'source_type' => 'embed',
                'title' => 'Escaped Embed',
            ],
        ])
            ->assertSee('&quot; onload=&quot;alert(1)', false)
            ->assertDontSee('onload="alert(1)"', false);
    }
}
