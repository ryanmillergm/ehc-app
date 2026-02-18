<?php

use App\Models\HomeSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(HomeSection::class)->constrained()->cascadeOnDelete();
            $table->string('item_key', 80)->nullable();
            $table->string('label')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('value')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['home_section_id', 'item_key', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
    }
};
