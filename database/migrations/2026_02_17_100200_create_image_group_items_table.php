<?php

use App\Models\Image;
use App\Models\ImageGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ImageGroup::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Image::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['image_group_id', 'image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_group_items');
    }
};
