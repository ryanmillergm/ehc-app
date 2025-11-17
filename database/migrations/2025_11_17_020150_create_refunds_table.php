<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            // Stripe identifiers
            $table->string('stripe_refund_id')->unique();   // re_...
            $table->string('charge_id')->nullable()->index(); // ch_...

            // Money
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('usd');

            // Status: pending|succeeded|failed|canceled
            $table->string('status')->index();
            $table->string('reason')->nullable();           // duplicate, requested_by_customer, etc.

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
