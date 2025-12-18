<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $table) {
            $table->id();

            // Optional link to a Laravel user
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('attempt_id')->nullable()->unique();

            // Stripe references
            $table->string('stripe_subscription_id')->nullable()->unique();   // sub_...
            $table->string('stripe_customer_id')->nullable()->index();        // cus_...
            $table->string('stripe_price_id')->nullable()->index();           // price_...

            $table->string('setup_intent_id')->nullable()->index();           // seti_...

            // Money + cadence
            $table->integer('amount_cents')->nullable();
            $table->string('currency', 10)->default('usd');
            $table->string('interval', 20)->default('month'); // month, year, etc.

            // Lifecycle
            // incomplete, incomplete_expired, trialing, active, past_due, canceled, unpaid
            $table->string('status')->default('incomplete')->index();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // Convenience / reporting
            $table->timestamp('last_pledge_at')->nullable();        // updated on invoice.paid
            $table->timestamp('next_pledge_at')->nullable();        // usually = current_period_end
            $table->string('latest_invoice_id')->nullable()->index();        // in_...
            $table->string('latest_payment_intent_id')->nullable()->index(); // pi_...

            // Donor hints (optional)
            $table->string('donor_email')->nullable()->index();
            $table->string('donor_name')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['stripe_customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
