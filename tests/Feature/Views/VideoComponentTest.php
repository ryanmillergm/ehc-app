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
            ->assertSee('youtube.com/embed/abc123');
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

