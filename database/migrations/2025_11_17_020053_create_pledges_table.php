<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $t) {
            $t->id();

            // Optional link to a Laravel user
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Stripe references
            $t->string('stripe_subscription_id')->nullable()->unique();   // sub_...
            $t->string('stripe_customer_id')->nullable()->index();        // cus_...
            $t->string('stripe_price_id')->nullable();                    // price_...

            // Money + cadence
            $t->integer('amount_cents')->nullable();
            $t->string('currency', 10)->default('usd');
            $t->string('interval', 20)->default('month');     // month, year, etc.

            // Lifecycle
            // incomplete, incomplete_expired, trialing, active, past_due, canceled, unpaid
            $t->string('status')->default('incomplete')->index();
            $t->boolean('cancel_at_period_end')->default(false);
            $t->timestamp('current_period_start')->nullable();
            $t->timestamp('current_period_end')->nullable();

            // Convenience / reporting
            $t->timestamp('last_pledge_at')->nullable();      // updated on invoice.paid
            $t->timestamp('next_pledge_at')->nullable();      // usually = current_period_end
            $t->string('latest_invoice_id')->nullable();      // in_...
            $t->string('latest_payment_intent_id')->nullable(); // pi_...

            // Donor hints (optional)
            $t->string('donor_email')->nullable();
            $t->string('donor_name')->nullable();

            $t->json('metadata')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
