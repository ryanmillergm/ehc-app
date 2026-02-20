<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_translations', function (Blueprint $table) {
            $table->string('template', 40)->default('standard')->after('content');
            $table->string('theme', 40)->default('default')->after('template');
            $table->string('hero_mode', 20)->default('none')->after('theme');

            $table->string('hero_title')->nullable()->after('hero_mode');
            $table->text('hero_subtitle')->nullable()->after('hero_title');
            $table->string('hero_cta_text')->nullable()->after('hero_subtitle');
            $table->string('hero_cta_url')->nullable()->after('hero_cta_text');

            $table->json('layout_data')->nullable()->after('hero_cta_url');

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
                'hero_title',
                'hero_subtitle',
                'hero_cta_text',
                'hero_cta_url',
                'layout_data',
                'published_at',
            ]);
        });
    }
};
