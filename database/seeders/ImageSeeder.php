<?php

namespace Database\Seeders;

use App\Models\Image;
use App\Models\ImageType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'path' => 'cms/legacy/sm/the-mayor.jpg',
                'public_url' => url('/images/sm/the-mayor.jpg'),
                'title' => 'The Mayor Outreach',
                'alt_text' => 'Bread of Grace outreach',
                'description' => 'Primary hero image used for homepage outreach storytelling.',
                'caption' => 'Primary outreach image',
                'type' => 'header',
                'is_decorative' => false,
            ],
            [
                'path' => 'cms/legacy/sm/lisa-hug.jpg',
                'public_url' => url('/images/sm/lisa-hug.jpg'),
                'title' => 'Lisa Hug',
                'alt_text' => 'Love and support',
                'description' => 'Featured image representing personal care and connection.',
                'caption' => 'Featured support image',
                'type' => 'featured',
                'is_decorative' => false,
            ],
            [
                'path' => 'cms/legacy/sm/group-joseph-peace.jpg',
                'public_url' => url('/images/sm/group-joseph-peace.jpg'),
                'title' => 'Group Joseph Peace',
                'alt_text' => 'Group ministry moment',
                'description' => 'Support image used in donation storytelling section.',
                'caption' => 'Donation section background',
                'type' => 'gallery',
                'is_decorative' => false,
            ],
            [
                'path' => 'cms/legacy/sm/bike-path-road.jpg',
                'public_url' => url('/images/sm/bike-path-road.jpg'),
                'title' => 'Bike Path Road',
                'alt_text' => 'Serve and outreach',
                'description' => 'Backdrop image used for volunteer call-to-action section.',
                'caption' => 'Serve section background',
                'type' => 'gallery',
                'is_decorative' => false,
            ],
            [
                'path' => 'cms/legacy/sm/bible-scriptures.jpg',
                'public_url' => url('/images/sm/bible-scriptures.jpg'),
                'title' => 'Bible Scriptures',
                'alt_text' => 'Bible and scriptures',
                'description' => 'Scripture-themed image for motivational band section.',
                'caption' => 'Parallax scripture image',
                'type' => 'gallery',
                'is_decorative' => true,
            ],
            [
                'path' => 'cms/legacy/favicons/android-icon-192x192.png',
                'public_url' => url('/images/favicons/android-icon-192x192.png'),
                'title' => 'Bread of Grace Logo',
                'alt_text' => 'Bread of Grace logo',
                'description' => 'Organization logo used in JSON-LD and brand references.',
                'caption' => 'Organization logo',
                'type' => 'logo',
                'is_decorative' => false,
            ],
        ];

        foreach ($rows as $row) {
            $type = ImageType::query()->firstOrCreate(
                ['slug' => Str::slug($row['type'])],
                ['name' => ucfirst($row['type']), 'is_active' => true]
            );

            Image::query()->updateOrCreate(
                [
                    'disk' => 'public',
                    'path' => $row['path'],
                ],
                [
                    'public_url' => $row['public_url'],
                    'extension' => pathinfo($row['public_url'], PATHINFO_EXTENSION),
                    'image_type_id' => $type->id,
                    'title' => $row['title'],
                    'alt_text' => $row['alt_text'],
                    'description' => $row['description'],
                    'caption' => $row['caption'],
                    'is_decorative' => $row['is_decorative'],
                    'is_active' => true,
                ]
            );
        }
    }
}
