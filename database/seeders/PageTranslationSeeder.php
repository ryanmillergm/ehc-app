<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PageTranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $page = Page::create([
            'title'     => 'Test translations Page',
            'is_active' => true,
        ]);

        $lang_en = Language::where('title', 'English')->first();
        if (empty($language)) {
            $language = Language::create([
                'title'         => 'English',
                'iso_code'      => 'en',
                'locale'        => 'en',
                'right_to_left' => false,
            ]);
        }

        $lang_es = Language::where('title', 'Spanish')->first();
        if (empty($language)) {
            $language = Language::create([
                'title'         => 'Spanish',
                'iso_code'      => 'es',
                'locale'        => 'es',
                'right_to_left' => false,
            ]);
        }

        $lang_fr = Language::where('title', 'French')->first();
        if (empty($language)) {
            $language = Language::create([
                'title'         => 'French',
                'iso_code'      => 'fr',
                'locale'        => 'fr',
                'right_to_left' => false,
            ]);
        }

        $lang_ar = Language::where('title', 'Arabic')->first();
        if (empty($language)) {
            $language = Language::create([
                'title'         => 'French',
                'iso_code'      => 'ar',
                'locale'        => 'ar',
                'right_to_left' => false,
            ]);
        }

        $atts_en = [
            'page_id'       => $page->id,
            'language_id'   => $lang_en->id,
            'title'         => 'Blog Test Title Example',
            'slug'          => 'blog-test-slug-english',
            'description'   => 'Blog Test Description Example',
            'content'       => '<h1>Blog Test Content Example</h1>',
            'is_active'     => true,
        ];

        $atts_es = [
            'page_id'       => $page->id,
            'language_id'   => $lang_es->id,
            'title'         => 'Ejemplo de título de prueba de blog',
            'slug'          => 'blog-test-slug-spanish',
            'description'   => 'Ejemplo de descripción de prueba de blog',
            'content'       => '<h1>Ejemplo de contenido de prueba de blog</h1>',
            'is_active'     => true,
        ];

        $atts_fr = [
            'page_id'       => $page->id,
            'language_id'   => $lang_fr->id,
            'title'         => 'Exemple de titre de test de blog',
            'slug'          => 'blog-test-slug-french',
            'description'   => 'Exemple de description de test de blog',
            'content'       => '<h1>Exemple de contenu de test de blog</h1>',
            'is_active'     => true,
        ];

        $atts_ar = [
            'page_id'       => $page->id,
            'language_id'   => $lang_ar->id,
            'title'         => 'مثال على عنوان اختبار المدونة',
            'slug'          => 'blog-test-slug-arabic',
            'description'   => 'مثال على وصف اختبار المدونة',
            'content'       => '<h1>مثال لمحتوى اختبار المدونة</h1>',
            'is_active'     => true,
        ];

        PageTranslation::create([$atts_en]);
        PageTranslation::create([$atts_es]);
        PageTranslation::create([$atts_fr]);
        PageTranslation::create([$atts_ar]);
    }
}
