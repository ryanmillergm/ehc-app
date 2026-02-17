<?php

use App\Models\ImageGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_groupables', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ImageGroup::class)->constrained()->cascadeOnDelete();
            $table->morphs('image_groupable');
            $table->string('role', 40)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['image_groupable_type', 'image_groupable_id', 'role'],
                'img_groupables_target_role_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_groupables');
    }
};
