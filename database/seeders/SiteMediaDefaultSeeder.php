<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\SiteMediaDefault;
use Illuminate\Database\Seeder;

class SiteMediaDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'header' => 'cms/legacy/sm/the-mayor.jpg',
            'featured' => 'cms/legacy/sm/lisa-hug.jpg',
            'og' => 'cms/legacy/sm/the-mayor.jpg',
            'thumbnail' => 'cms/legacy/sm/the-mayor.jpg',
        ];

        foreach ($defaults as $role => $path) {
            $image = Image::query()
                ->where('disk', 'public')
                ->where('path', $path)
                ->first();

            if (! $image) {
                continue;
            }

            SiteMediaDefault::query()->updateOrCreate(
                ['role' => $role],
                ['image_id' => $image->id]
            );
        }
    }
}
