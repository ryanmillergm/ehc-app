<?php

use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videoables', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Video::class)->constrained()->cascadeOnDelete();
            $table->morphs('videoable');
            $table->string('role', 40)->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['videoable_type', 'videoable_id', 'role'],
                'videoables_target_role_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videoables');
    }
};
