<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();

            // stable storage key in answers JSON: message, interests, experience_years
            $table->string('key')->unique();

            // text, textarea, select, radio, checkbox_group, toggle
            $table->string('type');

            $table->string('label');
            $table->text('help_text')->nullable();

            // config/options
            $table->json('config')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
