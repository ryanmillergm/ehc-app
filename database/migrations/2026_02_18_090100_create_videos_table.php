<?php

use App\Models\Image;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20)->index(); // embed|upload

            $table->string('embed_url')->nullable();

            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->string('public_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->foreignIdFor(Image::class, 'poster_image_id')->nullable()->constrained('images')->nullOnDelete();

            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_decorative')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
