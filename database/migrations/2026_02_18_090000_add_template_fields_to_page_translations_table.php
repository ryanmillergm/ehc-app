<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_translations', function (Blueprint $table) {
            $table->string('render_mode', 20)->default('template')->after('content');
            $table->string('template', 40)->default('standard')->after('render_mode');
            $table->string('theme', 40)->default('default')->after('template');
            $table->string('hero_mode', 20)->default('none')->after('theme');
            $table->string('hero_style', 20)->default('contained')->after('hero_mode');
            $table->string('hero_height', 10)->default('80')->after('hero_style');
            $table->string('hero_overlay', 20)->default('medium')->after('hero_height');
            $table->string('hero_text_align', 20)->default('left')->after('hero_overlay');
            $table->string('hero_text_width', 20)->default('normal')->after('hero_text_align');

            $table->text('hero_title')->nullable()->after('hero_text_width');
            $table->text('hero_subtitle')->nullable()->after('hero_title');
            $table->text('hero_cta_text')->nullable()->after('hero_subtitle');
            $table->string('hero_cta_url')->nullable()->after('hero_cta_text');

            $table->json('content_blocks')->nullable()->after('hero_cta_url');
            $table->longText('custom_html')->nullable()->after('content_blocks');
            $table->boolean('custom_html_is_trusted')->default(false)->after('custom_html');

            $table->timestamp('published_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('page_translations', function (Blueprint $table) {
            $table->dropColumn([
                'template',
                'theme',
                'hero_mode',
                'hero_style',
                'hero_height',
                'hero_overlay',
                'hero_text_align',
                'hero_text_width',
                'hero_title',
                'hero_subtitle',
                'hero_cta_text',
                'hero_cta_url',
                'content_blocks',
                'custom_html',
                'custom_html_is_trusted',
                'render_mode',
                'published_at',
            ]);
        });
    }
};
