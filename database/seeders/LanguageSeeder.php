<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        Language::query()->updateOrCreate(
            ['iso_code' => 'en'],
            [
                'title'         => 'English',
                'name'          => 'English',
                'iso_code'      => 'en',
                'locale'        => 'en',
                'right_to_left' => false,
            ]
        );
    }
}
