<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeRealFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_charge_succeeded_fixture_creates_subscription_tx_when_no_placeholder_exists(): void
    {
        $json  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $event = json_decode($json);

        $charge     = data_get($event, 'data.object');
        $customerId = data_get($charge, 'customer');

        $this->assertSame('charge.succeeded', $event->type);

        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => (int) data_get($charge, 'amount'),
            'currency'               => data_get($charge, 'currency', 'usd'),
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => 'sub_1SWjQ11d2H50O3q4TdQNgGjf',
            'donor_email'            => data_get($charge, 'billing_details.email'),
            'donor_name'             => data_get($charge, 'billing_details.name'),
        ]);

        (new StripeWebhookController())->handleEvent($event);

        // ✅ now expected: create OR enrich exactly one
        $this->assertDatabaseCount('transactions', 1);

        $tx = Transaction::firstOrFail();

        $this->assertSame($pledge->id, $tx->pledge_id);
        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('subscription_recurring', $tx->type);

        $this->assertSame(data_get($charge, 'payment_intent'), $tx->payment_intent_id);
        $this->assertSame(data_get($charge, 'id'), $tx->charge_id);
        $this->assertSame($customerId, $tx->customer_id);
        $this->assertSame(data_get($charge, 'payment_method'), $tx->payment_method_id);

        // receipt + invoice metadata (when present)
        if ($url = data_get($charge, 'receipt_url')) {
            $this->assertSame($url, $tx->receipt_url);
        }
        if ($invoiceId = data_get($charge, 'invoice')) {
            $this->assertSame($invoiceId, data_get($tx->metadata, 'stripe_invoice_id'));
        }

        // card metadata should exist if present in fixture
        $brand = data_get($charge, 'payment_method_details.card.brand');
        $last4 = data_get($charge, 'payment_method_details.card.last4');
        if ($brand) $this->assertSame($brand, data_get($tx->metadata, 'card_brand'));
        if ($last4) $this->assertSame($last4, data_get($tx->metadata, 'card_last4'));
    }

    public function test_real_charge_succeeded_fixture_enriches_placeholder_without_creating_a_second_row(): void
    {
        $json  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $event = json_decode($json);

        $charge          = data_get($event, 'data.object');
        $customerId      = data_get($charge, 'customer');
        $subscriptionId  = 'sub_1SWjQ11d2H50O3q4TdQNgGjf';

        $this->assertSame('charge.succeeded', $event->type);

        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'                => $user->id,
            'amount_cents'           => (int) data_get($charge, 'amount'),
            'currency'               => data_get($charge, 'currency', 'usd'),
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'donor_email'            => data_get($charge, 'billing_details.email'),
            'donor_name'             => data_get($charge, 'billing_details.name'),
        ]);

        $placeholder = Transaction::forceCreate([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'subscription_id'   => $subscriptionId,
            'customer_id'       => $customerId,

            'payment_intent_id' => null,
            'charge_id'         => null,
            'payment_method_id' => null,

            'amount_cents'      => $pledge->amount_cents,
            'currency'          => $pledge->currency,
            'type'              => 'subscription_recurring',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'paid_at'           => null,
            'receipt_url'       => null,
            'payer_email'       => null,
            'payer_name'        => null,
            'metadata'          => [],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        (new StripeWebhookController())->handleEvent($event);

        // ✅ must still be exactly one row
        $this->assertDatabaseCount('transactions', 1);

        $placeholder->refresh();

        $this->assertSame('succeeded', $placeholder->status);
        $this->assertSame(data_get($charge, 'payment_intent'), $placeholder->payment_intent_id);
        $this->assertSame(data_get($charge, 'id'), $placeholder->charge_id);
        $this->assertSame(data_get($charge, 'payment_method'), $placeholder->payment_method_id);

        // ✅ must not clobber user_id / must be same row
        $this->assertSame($user->id, $placeholder->user_id);

        // card metadata if present
        $brand = data_get($charge, 'payment_method_details.card.brand');
        $last4 = data_get($charge, 'payment_method_details.card.last4');
        if ($brand) $this->assertSame($brand, data_get($placeholder->metadata, 'card_brand'));
        if ($last4) $this->assertSame($last4, data_get($placeholder->metadata, 'card_last4'));
    }

    public function test_real_invoice_payment_succeeded_fixture_upserts_and_links_tx_and_invoice_receipt_url_wins(): void
    {
        $invoiceJson  = file_get_contents(base_path('tests/Fixtures/stripe/invoice.payment_succeeded.real.json'));
        $invoiceEvent = json_decode($invoiceJson);
        $invoice      = data_get($invoiceEvent, 'data.object');

        $invoiceId     = data_get($invoice, 'id');
        $customerId    = data_get($invoice, 'customer');
        $hostedInvoice = data_get($invoice, 'hosted_invoice_url');

        $subscriptionId =
            data_get($invoice, 'subscription')
            ?: data_get($invoice, 'lines.data.0.subscription')
            ?: data_get($invoice, 'lines.data.0.parent.subscription_item_details.subscription')
            ?: data_get($invoice, 'parent.subscription_details.subscription');

        $amountPaid = (int) (data_get($invoice, 'amount_paid') ?? data_get($invoice, 'amount_due'));
        $currency   = data_get($invoice, 'currency', 'usd');

        $pledge = Pledge::forceCreate([
            'user_id'                => null,
            'amount_cents'           => $amountPaid,
            'currency'               => $currency,
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'donor_email'            => data_get($invoice, 'customer_email'),
            'donor_name'             => 'Ryan Miller',
        ]);

        // Seed an “early” row that invoice should enrich (no duplication)
        $chargeJson  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $chargeEvent = json_decode($chargeJson);
        $charge      = data_get($chargeEvent, 'data.object');

        $earlyPiId     = data_get($charge, 'payment_intent');
        $earlyChargeId = data_get($charge, 'id');
        $earlyPmId     = data_get($charge, 'payment_method');

        Transaction::forceCreate([
            'user_id'           => null,
            'pledge_id'         => $pledge->id,
            'type'              => 'subscription_recurring',
            'status'            => 'succeeded',
            'payment_intent_id' => $earlyPiId,
            'charge_id'         => $earlyChargeId,
            'customer_id'       => $customerId,
            'payment_method_id' => $earlyPmId,
            'amount_cents'      => $amountPaid,
            'currency'          => $currency,

            'subscription_id'   => null,
            'receipt_url'       => null,
            'metadata'          => [],

            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        (new StripeWebhookController())->handleEvent($invoiceEvent);

        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $tx = Transaction::where('pledge_id', $pledge->id)->firstOrFail();

        $this->assertSame($subscriptionId, $tx->subscription_id);
        $this->assertSame($invoiceId, data_get($tx->metadata, 'stripe_invoice_id'));

        if ($hostedInvoice) {
            // ✅ invoice URL should win over charge receipt URL
            $this->assertSame($hostedInvoice, $tx->receipt_url);
        }

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
    }
}
