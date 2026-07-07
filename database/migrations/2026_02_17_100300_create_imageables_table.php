<?php

use App\Models\Image;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imageables', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Image::class)->constrained()->cascadeOnDelete();
            $table->morphs('imageable');
            $table->string('role', 40)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['imageable_type', 'imageable_id', 'role'],
                'imageables_target_role_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imageables');
    }
};
