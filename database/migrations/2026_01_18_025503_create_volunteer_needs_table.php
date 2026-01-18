<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('volunteer_needs', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('event_id')->nullable()->index();

            $table->unsignedInteger('capacity')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_needs');
    }
};
