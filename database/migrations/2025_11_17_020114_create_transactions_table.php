<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Optional link to a Laravel user
            $table->foreignId('user_id')->nullable()
                ->constrained()
                ->nullOnDelete();

            // Link to pledge (for recurring)
            $table->foreignId('pledge_id')->nullable()
                ->constrained('pledges')
                ->nullOnDelete();

            $table->string('attempt_id')->nullable()->index();

            // Stripe identifiers
            $table->string('payment_intent_id')->nullable()->unique();   // pi_...
            $table->string('subscription_id')->nullable()->index();      // sub_...
            $table->string('charge_id')->nullable()->unique();           // ch_...
            $table->string('customer_id')->nullable()->index();          // cus_...
            $table->string('payment_method_id')->nullable()->index();    // pm_...

            // Money
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('usd');

            // Type: one_time | subscription_initial | subscription_recurring | refund
            $table->string('type')->default('one_time')->index();

            // Status lifecycle
            // pending|requires_action|processing|succeeded|canceled|failed
            $table->string('status')->index();

            // Payer / receipt
            $table->string('payer_email')->nullable()->index();
            $table->string('payer_name')->nullable();
            $table->string('receipt_url')->nullable();

            // Optional extras
            $table->string('source')->nullable()->index();
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['created_at']);

            $table->index(['pledge_id', 'type', 'created_at']);
            $table->index(['subscription_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
