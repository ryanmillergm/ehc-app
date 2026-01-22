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

    public function test_real_charge_succeeded_fixture_creates_one_time_tx_when_invoice_is_null(): void
    {
        $json  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $event = json_decode($json);

        $charge     = data_get($event, 'data.object');
        $customerId = data_get($charge, 'customer');

        $this->assertSame('charge.succeeded', $event->type);

        // The real fixture you're using has invoice = null, so this is treated as a one-time charge event.
        $this->assertNull(data_get($charge, 'invoice'));

        // Create a pledge so webhook has something to link to via customer_id fallback (if your controller does that).
        $pledge = Pledge::forceCreate([
            'user_id'            => null,
            'amount_cents'       => (int) data_get($charge, 'amount'),
            'currency'           => data_get($charge, 'currency', 'usd'),
            'interval'           => 'month',
            'status'             => 'incomplete',
            'stripe_customer_id' => $customerId,
            'donor_email'        => data_get($charge, 'billing_details.email'),
            'donor_name'         => data_get($charge, 'billing_details.name'),
        ]);

        (new StripeWebhookController())->handleEvent($event);

        $this->assertDatabaseCount('transactions', 1);

        $tx = Transaction::firstOrFail();

        // Depending on your controller, it may link pledge_id via customer fallback.
        $this->assertSame($pledge->id, $tx->pledge_id);

        $this->assertSame('succeeded', $tx->status);
        $this->assertSame('one_time', $tx->type);

        $this->assertSame(data_get($charge, 'payment_intent'), $tx->payment_intent_id);
        $this->assertSame(data_get($charge, 'id'), $tx->charge_id);
        $this->assertSame($customerId, $tx->customer_id);
        $this->assertSame(data_get($charge, 'payment_method'), $tx->payment_method_id);

        if ($url = data_get($charge, 'receipt_url')) {
            $this->assertSame($url, $tx->receipt_url);
        }

        // Card metadata should exist if present in fixture
        $brand = data_get($charge, 'payment_method_details.card.brand');
        $last4 = data_get($charge, 'payment_method_details.card.last4');

        if ($brand) $this->assertSame($brand, data_get($tx->metadata, 'card_brand'));
        if ($last4) $this->assertSame($last4, data_get($tx->metadata, 'card_last4'));
    }

    public function test_real_charge_succeeded_fixture_enriches_placeholder_without_creating_a_second_row(): void
    {
        $json  = file_get_contents(base_path('tests/Fixtures/stripe/charge.succeeded.real.json'));
        $event = json_decode($json);

        $charge     = data_get($event, 'data.object');
        $customerId = data_get($charge, 'customer');

        $this->assertSame('charge.succeeded', $event->type);
        $this->assertNull(data_get($charge, 'invoice'));

        $user = User::factory()->create();

        $pledge = Pledge::forceCreate([
            'user_id'            => $user->id,
            'amount_cents'       => (int) data_get($charge, 'amount'),
            'currency'           => data_get($charge, 'currency', 'usd'),
            'interval'           => 'month',
            'status'             => 'incomplete',
            'stripe_customer_id' => $customerId,
            'donor_email'        => data_get($charge, 'billing_details.email'),
            'donor_name'         => data_get($charge, 'billing_details.name'),
        ]);

        // Placeholder row: in production this could be created by your app before Stripe sends charge.succeeded.
        // Keep it one_time so the enrichment path is unambiguous.
        $placeholder = Transaction::forceCreate([
            'user_id'           => $user->id,
            'pledge_id'         => $pledge->id,
            'customer_id'       => $customerId,

            'payment_intent_id' => data_get($charge, 'payment_intent'),
            'charge_id'         => null,
            'payment_method_id' => null,

            'amount_cents'      => $pledge->amount_cents,
            'currency'          => $pledge->currency,
            'type'              => 'one_time',
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

        // Must still be exactly one row (no duplicates)
        $this->assertDatabaseCount('transactions', 1);

        $placeholder->refresh();

        $this->assertSame('succeeded', $placeholder->status);
        $this->assertSame(data_get($charge, 'id'), $placeholder->charge_id);
        $this->assertSame(data_get($charge, 'payment_method'), $placeholder->payment_method_id);

        // Must not clobber user_id / must be same row
        $this->assertSame($user->id, $placeholder->user_id);

        if ($url = data_get($charge, 'receipt_url')) {
            $this->assertSame($url, $placeholder->receipt_url);
        }

        // Card metadata if present
        $brand = data_get($charge, 'payment_method_details.card.brand');
        $last4 = data_get($charge, 'payment_method_details.card.last4');

        if ($brand) $this->assertSame($brand, data_get($placeholder->metadata, 'card_brand'));
        if ($last4) $this->assertSame($last4, data_get($placeholder->metadata, 'card_last4'));
    }

    public function test_real_invoice_payment_succeeded_fixture_upserts_by_invoice_id_and_does_not_duplicate(): void
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

        // Key the placeholder by INVOICE ID, because your handler's canonical match for invoice events is invoice id.
        $placeholder = Transaction::forceCreate([
            'user_id'           => null,
            'pledge_id'         => $pledge->id,
            'subscription_id'   => $subscriptionId,
            'customer_id'       => $customerId,

            // Leave PI/charge null so we prove invoice-id matching works even when Stripe omits them.
            'payment_intent_id' => null,
            'charge_id'         => null,
            'payment_method_id' => null,

            'amount_cents'      => $amountPaid,
            'currency'          => $currency,
            'type'              => 'subscription_recurring',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'paid_at'           => null,
            'receipt_url'       => null,
            'payer_email'       => null,
            'payer_name'        => null,
            'metadata'          => [
                'stripe_invoice_id' => $invoiceId,
                'stage' => 'awaiting_invoice',
            ],
            'stripe_invoice_id' => $invoiceId,

            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        (new StripeWebhookController())->handleEvent($invoiceEvent);

        // Must be exactly one row: invoice handler must enrich placeholder, not insert another.
        $this->assertSame(1, Transaction::where('pledge_id', $pledge->id)->count());

        $placeholder->refresh();

        $this->assertSame('succeeded', $placeholder->status);
        $this->assertSame($subscriptionId, $placeholder->subscription_id);
        $this->assertSame($invoiceId, $placeholder->stripe_invoice_id);
        $this->assertSame($invoiceId, data_get($placeholder->metadata, 'stripe_invoice_id'));

        if ($hostedInvoice) {
            // invoice URL should win if present
            $this->assertSame($hostedInvoice, $placeholder->receipt_url);
        }

        $pledge->refresh();
        $this->assertSame('active', $pledge->status);
        $this->assertNotNull($pledge->current_period_start);
        $this->assertNotNull($pledge->current_period_end);
    }
}
