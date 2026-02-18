<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        $filename = Str::uuid()->toString() . '.mp4';
        $path = 'cms/videos/' . now()->format('Y/m') . '/' . $filename;

        return [
            'source_type' => 'upload',
            'embed_url' => null,
            'disk' => 'public',
            'path' => $path,
            'public_url' => '/storage/' . $path,
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size_bytes' => 2_000_000,
            'duration_seconds' => 45,
            'poster_image_id' => null,
            'title' => $this->faker->sentence(4),
            'alt_text' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(2),
            'is_decorative' => false,
            'is_active' => true,
        ];
    }

    public function embed(): static
    {
        return $this->state(fn () => [
            'source_type' => 'embed',
            'embed_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'disk' => null,
            'path' => null,
            'public_url' => null,
            'mime_type' => null,
            'extension' => null,
            'size_bytes' => null,
        ]);
    }
}
