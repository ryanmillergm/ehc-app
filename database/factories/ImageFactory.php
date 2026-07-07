<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\ImageType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        $filename = Str::uuid()->toString() . '.jpg';
        $path = 'cms/images/' . now()->format('Y/m') . '/' . $filename;

        return [
            'disk' => 'public',
            'path' => $path,
            'public_url' => '/storage/' . $path,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 120_000,
            'width' => 1400,
            'height' => 900,
            'image_type_id' => ImageType::factory(),
            'title' => $this->faker->sentence(4),
            'alt_text' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(2),
            'caption' => $this->faker->sentence(8),
            'credit' => $this->faker->name(),
            'is_decorative' => false,
            'is_active' => true,
        ];
    }
}
