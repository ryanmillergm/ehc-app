<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Seeder;

class PageTranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ---------------------------------------------------------------------
        // Ensure Languages exist (matches LanguageSeeder)
        // ---------------------------------------------------------------------
        $en = Language::firstOrCreate(
            ['locale' => 'en'],
            ['title' => 'English', 'iso_code' => 'en', 'right_to_left' => false]
        );

        $es = Language::firstOrCreate(
            ['locale' => 'es'],
            ['title' => 'Spanish', 'iso_code' => 'es', 'right_to_left' => false]
        );

        $fr = Language::firstOrCreate(
            ['locale' => 'fr'],
            ['title' => 'French', 'iso_code' => 'fr', 'right_to_left' => false]
        );

        $ar = Language::firstOrCreate(
            ['locale' => 'ar'],
            ['title' => 'Arabic', 'iso_code' => 'ar', 'right_to_left' => true]
        );

        // Helper to upsert a translation per page/lang
        $make = function (Page $page, Language $lang, array $atts) {
            PageTranslation::updateOrCreate(
                [
                    'page_id'     => $page->id,
                    'language_id' => $lang->id,
                ],
                $atts + [
                    'page_id'     => $page->id,
                    'language_id' => $lang->id,
                ]
            );
        };

        // ---------------------------------------------------------------------
        // 0) Test translations Page (ALL languages) – keeps old test strings
        // ---------------------------------------------------------------------
        $testPage = Page::firstOrCreate(
            ['title' => 'Test translations Page'],
            ['is_active' => true]
        );

        $make($testPage, $en, [
            'title'       => 'Blog Test Title Example',
            'slug'        => 'blog-test-slug-english',
            'description' => 'Blog Test Description Example',
            'content'     => '<h1>Blog Test Content Example</h1>',
            'is_active'   => true,
        ]);

        $make($testPage, $es, [
            'title'       => 'Ejemplo de título de prueba de blog',
            'slug'        => 'blog-test-slug-spanish',
            'description' => 'Ejemplo de descripción de prueba de blog',
            'content'     => '<h1>Ejemplo de contenido de prueba de blog</h1>',
            'is_active'   => true,
        ]);

        $make($testPage, $fr, [
            'title'       => 'Exemple de titre de test de blog',
            'slug'        => 'blog-test-slug-french',
            'description' => 'Exemple de description de test de blog',
            'content'     => '<h1>Exemple de contenu de test de blog</h1>',
            'is_active'   => true,
        ]);

        $make($testPage, $ar, [
            'title'       => 'مثال على عنوان اختبار المدونة',
            'slug'        => 'blog-test-slug-arabic',
            'description' => 'مثال على وصف اختبار المدونة',
            'content'     => '<h1>مثال لمحتوى اختبار المدونة</h1>',
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------------
        // 1) About Us (ALL languages)
        // ---------------------------------------------------------------------
        $about = Page::firstOrCreate(
            ['title' => 'About Us'],
            ['is_active' => true]
        );

        $make($about, $en, [
            'title'       => 'About Us',
            'slug'        => 'about-us',
            'description' => 'Learn who we are and why we exist.',
            'content'     => <<<HTML
                <h2>Our Mission</h2>
                <p>We exist to serve people with compassion and clarity.</p>
                <p>We believe generosity changes lives.</p>
                HTML,
            'is_active'   => true,
        ]);

        $make($about, $es, [
            'title'       => 'Sobre Nosotros',
            'slug'        => 'sobre-nosotros',
            'description' => 'Conoce quiénes somos y por qué existimos.',
            'content'     => <<<HTML
                <h2>Nuestra Misión</h2>
                <p>Existimos para servir a las personas con compasión y claridad.</p>
                <p>Creemos que la generosidad cambia vidas.</p>
                HTML,
            'is_active'   => true,
        ]);

        $make($about, $fr, [
            'title'       => 'À Propos',
            'slug'        => 'a-propos',
            'description' => 'Découvrez qui nous sommes et pourquoi nous existons.',
            'content'     => <<<HTML
                <h2>Notre Mission</h2>
                <p>Nous existons pour servir les gens avec compassion et clarté.</p>
                <p>Nous croyons que la générosité change des vies.</p>
                HTML,
            'is_active'   => true,
        ]);

        $make($about, $ar, [
            'title'       => 'معلومات عنا',
            'slug'        => 'معلومات-عنا',
            'description' => 'تعرّف على من نحن ولماذا نحن هنا.',
            'content'     => <<<HTML
                <h2>مهمتنا</h2>
                <p>نحن هنا لخدمة الناس بالرحمة والوضوح.</p>
                <p>نؤمن أن العطاء يغيّر الحياة.</p>
                HTML,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------------
        // 2) Donate (EN + ES only) -> missing FR/AR to test fallback to EN
        // ---------------------------------------------------------------------
        $donate = Page::firstOrCreate(
            ['title' => 'Donate'],
            ['is_active' => true]
        );

        $make($donate, $en, [
            'title'       => 'Donate',
            'slug'        => 'donate',
            'description' => 'Your gift helps us reach more people.',
            'content'     => <<<HTML
                <h2>Give Today</h2>
                <p>Every gift supports real work on the ground.</p>
                <p>Thank you for partnering with us.</p>
                HTML,
            'is_active'   => true,
        ]);

        $make($donate, $es, [
            'title'       => 'Donar',
            'slug'        => 'donar',
            'description' => 'Tu ofrenda nos ayuda a llegar a más personas.',
            'content'     => <<<HTML
                <h2>Da Hoy</h2>
                <p>Cada donación apoya trabajo real en el campo.</p>
                <p>Gracias por colaborar con nosotros.</p>
                HTML,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------------
        // 3) Events (EN + FR only) -> missing ES to test fallback to EN
        // ---------------------------------------------------------------------
        $events = Page::firstOrCreate(
            ['title' => 'Events'],
            ['is_active' => true]
        );

        $make($events, $en, [
            'title'       => 'Events',
            'slug'        => 'events',
            'description' => 'See what’s coming up next.',
            'content'     => <<<HTML
                <h2>Upcoming Events</h2>
                <ul>
                <li>Summer Outreach – July 25</li>
                <li>Community Night – August 12</li>
                </ul>
                HTML,
            'is_active'   => true,
        ]);

        $make($events, $fr, [
            'title'       => 'Événements',
            'slug'        => 'evenements',
            'description' => 'Découvrez nos prochains rendez-vous.',
            'content'     => <<<HTML
                <h2>Événements à Venir</h2>
                <ul>
                <li>Action d’été – 25 juillet</li>
                <li>Soirée communautaire – 12 août</li>
                </ul>
                HTML,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------------
        // 4) Privacy Policy (EN only) -> all other languages fallback to EN
        // ---------------------------------------------------------------------
        $privacy = Page::firstOrCreate(
            ['title' => 'Privacy Policy'],
            ['is_active' => true]
        );

        $make($privacy, $en, [
            'title'       => 'Privacy Policy',
            'slug'        => 'privacy-policy',
            'description' => 'How we handle and protect your data.',
            'content'     => <<<HTML
                <h2>Your Privacy Matters</h2>
                <p>We only collect data needed to provide services and process donations.</p>
                <p>We never sell your personal information.</p>
                HTML,
            'is_active'   => true,
        ]);

        // ---------------------------------------------------------------------
        // 5) Internal Draft Page (EN translation exists but page inactive)
        // ---------------------------------------------------------------------
        $draft = Page::firstOrCreate(
            ['title' => 'Internal Draft Page'],
            ['is_active' => false]
        );

        $make($draft, $en, [
            'title'       => 'Internal Draft Page',
            'slug'        => 'internal-draft-page',
            'description' => 'Not visible to the public.',
            'content'     => '<h2>Draft</h2><p>This page is inactive.</p>',
            'is_active'   => true,
        ]);
    }
}
