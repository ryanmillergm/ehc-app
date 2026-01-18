<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_need_id')->constrained('volunteer_needs')->cascadeOnDelete();

            $table->string('status')->default('submitted')->index();

            $table->text('message');

            $table->json('interests')->nullable();
            $table->json('answers')->nullable();
            $table->json('availability')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            // no duplicate app per user per need
            $table->unique(['user_id', 'volunteer_need_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_applications');
    }
};
