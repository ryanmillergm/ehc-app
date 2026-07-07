<?php

namespace Database\Seeders;

use App\Models\ImageType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImageTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Header',
            'Featured',
            'Gallery',
            'Product',
            'Logo',
            'SEO',
        ];

        foreach ($types as $name) {
            ImageType::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $name . ' image classification',
                    'is_active' => true,
                ]
            );
        }
    }
}
