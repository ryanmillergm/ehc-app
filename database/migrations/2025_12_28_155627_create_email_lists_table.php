<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_lists', function (Blueprint $table) {
            $table->id();

            $table->string('key')->unique();              // newsletter, events, blog, updates
            $table->string('label');                      // Newsletter, Events, Blog, Updates
            $table->text('description')->nullable();

            $table->string('purpose')->default('marketing'); // marketing|transactional
            $table->boolean('is_default')->default(false);
            $table->boolean('is_opt_outable')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_lists');
    }
};
