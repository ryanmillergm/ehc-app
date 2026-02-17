<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\ImageGroup;
use App\Models\ImageGroupItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImageGroupItem>
 */
class ImageGroupItemFactory extends Factory
{
    protected $model = ImageGroupItem::class;

    public function definition(): array
    {
        return [
            'image_group_id' => ImageGroup::factory(),
            'image_id' => Image::factory(),
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
