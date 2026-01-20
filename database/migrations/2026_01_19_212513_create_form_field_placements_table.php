<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('form_field_placements', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner (ApplicationForm now, others later)
            $table->string('fieldable_type');
            $table->unsignedBigInteger('fieldable_id');

            $table->foreignId('form_field_id')
                ->constrained('form_fields')
                ->cascadeOnDelete();

            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);

            // Placement-level overrides
            $table->string('label_override')->nullable();
            $table->text('help_text_override')->nullable();
            $table->json('config_override')->nullable();

            $table->timestamps();

            // Indexes (EXPLICIT NAMES to avoid MySQL 64-char limit)
            $table->index(
                ['fieldable_type', 'fieldable_id'],
                'ffp_fieldable_idx'
            );

            $table->unique(
                ['fieldable_type', 'fieldable_id', 'form_field_id'],
                'ffp_unique_field_per_owner'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_field_placements');
    }
};
