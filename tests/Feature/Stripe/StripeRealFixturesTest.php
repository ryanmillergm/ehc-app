<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeRealFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_charge_succeeded_fixture_creates_early_recurring_tx(): void
    {
        $json  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $event = json_decode($json);

        $charge      = data_get($event, 'data.object');
        $piId        = data_get($charge, 'payment_intent');
        $chargeId    = data_get($charge, 'id');
        $customerId  = data_get($charge, 'customer');
        $paymentMethodId = data_get($charge, 'payment_method');
        $amount      = data_get($charge, 'amount');
        $currency    = data_get($charge, 'currency', 'usd');

        $pledge = Pledge::forceCreate([
            'amount_cents'           => $amount,
            'currency'               => $currency,
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => $customerId,
            // match the subscription seen in your invoice fixture
            'stripe_subscription_id' => 'sub_1SWjQ11d2H50O3q4TdQNgGjf',
            'donor_email'            => 'ryanmillergm@gmail.com',
            'donor_name'             => 'Ryan Miller',
        ]);

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseHas('transactions', [
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => $piId,
            'charge_id'         => $chargeId,
            'customer_id'       => $customerId,
            'payment_method_id' => $paymentMethodId,
        ]);
    }

    public function test_real_invoice_payment_succeeded_fixture_upserts_and_links_tx(): void
    {
        // Load invoice fixture
        $invoiceJson  = file_get_contents(base_path('tests/Fixtures/stripe/invoice.payment_succeeded.real.json'));
        $invoiceEvent = json_decode($invoiceJson);
        $invoice      = data_get($invoiceEvent, 'data.object');

        $invoiceId      = data_get($invoice, 'id');
        $customerId     = data_get($invoice, 'customer');
        $subscriptionId =
            data_get($invoice, 'subscription')
            ?: data_get($invoice, 'lines.data.0.subscription')
            ?: data_get($invoice, 'lines.data.0.parent.subscription_item_details.subscription')
            ?: data_get($invoice, 'parent.subscription_details.subscription');

        $amountPaid = data_get($invoice, 'amount_paid') ?? data_get($invoice, 'amount_due');
        $currency   = data_get($invoice, 'currency', 'usd');

        // Create pledge that the invoice should resolve
        $pledge = Pledge::forceCreate([
            'amount_cents'           => $amountPaid,
            'currency'               => $currency,
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'donor_email'            => 'ryanmillergm@gmail.com',
            'donor_name'             => 'Ryan Miller',
        ]);

        // Create "early charge" tx that invoice should upsert/link to.
        // Pull PI/charge from the real charge fixture so it matches reality.
        $chargeJson  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $chargeEvent = json_decode($chargeJson);
        $charge      = data_get($chargeEvent, 'data.object');

        $earlyPiId     = data_get($charge, 'payment_intent');
        $earlyChargeId = data_get($charge, 'id');
        $earlyPmId     = data_get($charge, 'payment_method');

        Transaction::forceCreate([
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => $earlyPiId,
            'charge_id'         => $earlyChargeId,
            'customer_id'       => $customerId,
            'payment_method_id' => $earlyPmId,
            'amount_cents'      => $amountPaid,
            'currency'          => $currency,
            // leave subscription_id null to simulate "early" tx
            'subscription_id'   => null,
            'metadata'          => [],
        ]);

        (new StripeWebhookController())->handleEvent($invoiceEvent);

        $this->assertDatabaseHas('transactions', [
            'pledge_id'       => $pledge->id,
            'type'            => 'subscription_recurring',
            'status'          => 'succeeded',
            'payment_intent_id' => $earlyPiId,       // stays from early charge
            'charge_id'         => $earlyChargeId,   // stays from early charge
            'subscription_id'   => $subscriptionId,  // filled by invoice
            'customer_id'       => $customerId,
            'metadata->stripe_invoice_id' => $invoiceId,
        ]);

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
    }
}
