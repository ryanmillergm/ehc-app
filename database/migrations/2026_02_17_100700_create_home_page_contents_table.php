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
        Schema::create('home_page_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Language::class)->nullable()->constrained()->nullOnDelete();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('hero_intro')->nullable();
            $table->string('meeting_schedule')->nullable();
            $table->string('meeting_location')->nullable();
            $table->foreignIdFor(Image::class, 'hero_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->foreignIdFor(Image::class, 'featured_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->foreignIdFor(Image::class, 'og_image_id')->nullable()->constrained('images')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_page_contents');
    }
};
