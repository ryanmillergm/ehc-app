<?php

namespace Tests\Unit\Media;

use App\Services\Media\VideoMetadataExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoMetadataExtractorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_extracts_basic_metadata_for_existing_disk_file(): void
    {
        Storage::disk('public')->put('cms/videos/2026/02/meta-test.mp4', str_repeat('x', 4096));

        $metadata = app(VideoMetadataExtractor::class)->extractFromDiskPath('public', 'cms/videos/2026/02/meta-test.mp4');

        $this->assertSame('mp4', $metadata['extension']);
        $this->assertIsInt($metadata['size_bytes']);
        $this->assertGreaterThan(0, $metadata['size_bytes']);
        $this->assertArrayHasKey('mime_type', $metadata);
        $this->assertArrayHasKey('duration_seconds', $metadata);
    }

    #[Test]
    public function it_returns_safe_nulls_when_file_is_missing(): void
    {
        $metadata = app(VideoMetadataExtractor::class)->extractFromDiskPath('public', 'cms/videos/missing-file.webm');

        $this->assertSame('webm', $metadata['extension']);
        $this->assertNull($metadata['mime_type']);
        $this->assertNull($metadata['size_bytes']);
        $this->assertNull($metadata['duration_seconds']);
    }
}

