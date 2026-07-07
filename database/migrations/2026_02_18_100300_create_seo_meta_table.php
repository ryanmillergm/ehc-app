<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_meta', function (Blueprint $table): void {
            $table->id();
            $table->string('seoable_type');
            $table->unsignedBigInteger('seoable_id')->default(0);
            $table->string('target_key')->default('');
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_og_image', 500)->nullable();
            $table->string('canonical_path', 255)->nullable();
            $table->string('robots', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id', 'target_key', 'language_id'], 'seo_meta_unique_target_lang');
            $table->index(['seoable_type', 'seoable_id', 'is_active'], 'seo_meta_type_id_active_idx');
            $table->index(['target_key', 'is_active'], 'seo_meta_target_key_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
