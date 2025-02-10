<?php

use App\Models\Language;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Page::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Language::class)->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description');
            $table->text('content');
            $table->boolean('is_active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};
