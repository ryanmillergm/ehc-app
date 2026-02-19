<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_seos', function (Blueprint $table): void {
            $table->id();
            $table->string('route_key');
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_og_image', 500)->nullable();
            $table->string('canonical_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['route_key', 'language_id']);
            $table->index(['route_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_seos');
    }
};
