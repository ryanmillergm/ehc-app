<?php

use App\Models\Image;
use App\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Language::class)->nullable()->constrained()->nullOnDelete();
            $table->string('section_key', 80);
            $table->string('eyebrow')->nullable();
            $table->string('heading')->nullable();
            $table->string('subheading')->nullable();
            $table->text('body')->nullable();
            $table->text('note')->nullable();
            $table->string('cta_primary_label')->nullable();
            $table->string('cta_primary_url')->nullable();
            $table->string('cta_secondary_label')->nullable();
            $table->string('cta_secondary_url')->nullable();
            $table->string('cta_tertiary_label')->nullable();
            $table->string('cta_tertiary_url')->nullable();
            $table->foreignIdFor(Image::class)->nullable()->constrained('images')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['language_id', 'section_key']);
            $table->index(['section_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
