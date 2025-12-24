<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_subscribers', function (Blueprint $table) {
            $table->id();

            $table->string('email')->unique();
            $table->string('name')->nullable();

            // future-friendly: connect a subscriber to a real user later (optional)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // future-friendly: what checkbox list they want later (newsletter/updates/events/etc.)
            $table->json('preferences')->nullable();

            $table->string('unsubscribe_token', 64)->unique();

            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_subscribers');
    }
};
