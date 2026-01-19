<?php

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
        Schema::create('application_form_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('application_form_id')->constrained()->cascadeOnDelete();

            // e.g. text, textarea, select, radio, checkbox_group, toggle
            $table->string('type');

            // stable storage key in answers json: e.g. message, interests, experience_years
            $table->string('key');

            $table->string('label');
            $table->text('help_text')->nullable();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort')->default(0);

            // options/config, e.g. { "options": { "food": "Food service" } }
            $table->json('config')->nullable();

            $table->timestamps();

            $table->unique(['application_form_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_form_fields');
    }
};
