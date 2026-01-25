<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\Stripe\StripeEventFixture;

class SubscriptionCheckoutEventOrderingTest extends TestCase
{
    use RefreshDatabase;

    public static function eventOrders(): array
    {
        return [
            // Stripe is “helpful” and sends the canonical invoice event first.
            'invoice_first' => [
                ['invoice.paid', 'payment_intent.succeeded', 'charge.succeeded'],
            ],

            // Stripe sends non-writers first (these should NOT mark paid).
            'pi_then_charge_then_invoice' => [
                ['payment_intent.succeeded', 'charge.succeeded', 'invoice.paid'],
            ],

            // Stripe Basil “invoice_payment.paid” arrives (in tests it won’t retrieve invoice),
            // then invoice arrives and is the canonical writer.
            'invoice_payment_paid_then_invoice' => [
                ['invoice_payment.paid', 'invoice.paid'],
            ],

            // Everything twice (concurrency / retries / duplication).
            'duplicate_storm' => [
                ['payment_intent.succeeded', 'invoice.paid', 'invoice.paid', 'charge.succeeded', 'payment_intent.succeeded'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('eventOrders')]
    public function it_is_event_order_resilient_and_idempotent(array $sequence): void
    {
        // Arrange: the exact world your start(monthly) creates
        $attemptId = 'attempt_abc_123';

        $pledge = Pledge::query()->create([
            'attempt_id'             => $attemptId,
            'amount_cents'           => 15000,
            'currency'               => 'usd',
            'interval'               => 'month',
            'status'                 => 'incomplete',
            'stripe_customer_id'     => 'cus_123',
            'stripe_subscription_id' => 'sub_123',
            'setup_intent_id'        => 'seti_123',
            'donor_email'            => 'donor@example.test',
            'donor_name'             => 'Donor Person',
        ]);

        $placeholder = Transaction::query()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $attemptId,
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'amount_cents'      => 15000,
            'currency'          => 'usd',
            'subscription_id'   => 'sub_123',
            'setup_intent_id'   => 'seti_123',
            'stripe_invoice_id' => null,
            'payment_intent_id' => null,
            'charge_id'         => null,
        ]);

        $this->assertSame(1, Transaction::count());
        $this->assertSame('pending', $placeholder->status);

        // Canonical IDs we want to reconcile to the placeholder
        $invoiceId = 'in_123';
        $piId      = 'pi_123';
        $chargeId  = 'ch_123';

        // Act: send events in the requested order.
        foreach ($sequence as $i => $eventName) {
            $event = $this->buildEventFromFixtureName(
                $eventName,
                eventId: 'evt_test_' . ($i + 1),
                invoiceId: $invoiceId,
                subscriptionId: 'sub_123',
                customerId: 'cus_123',
                paymentIntentId: $piId,
                chargeId: $chargeId,
                amountPaid: 15000,
            );

            $res = $this->postJson('/stripe/webhook', $event);
            $res->assertOk();
        }

        // Assert: still one tx, and ONLY invoice.* should have marked it succeeded.
        $this->assertSame(1, Transaction::count());

        $placeholder->refresh();

        // If the sequence contained an invoice writer, the placeholder must be finalized.
        $invoiceWriterWasSent = in_array('invoice.paid', $sequence, true) || in_array('invoice.payment_succeeded', $sequence, true);

        if ($invoiceWriterWasSent) {
            $this->assertSame('succeeded', $placeholder->status);
            $this->assertSame($invoiceId, $placeholder->stripe_invoice_id);
            $this->assertSame($piId, $placeholder->payment_intent_id);
            $this->assertSame($chargeId, $placeholder->charge_id);
            $this->assertNotNull($placeholder->paid_at);

            // setup intent carried through (the “anchor row” guarantee)
            $this->assertSame('seti_123', $placeholder->setup_intent_id);
        } else {
            // If invoice didn't arrive, it must NOT be marked succeeded by other events.
            $this->assertSame('pending', $placeholder->status);
        }

        // Extra: blast the same invoice writer again and prove idempotency (no duplicates).
        if ($invoiceWriterWasSent) {
            $again = $this->buildEventFromFixtureName(
                'invoice.paid',
                eventId: 'evt_test_again',
                invoiceId: $invoiceId,
                subscriptionId: 'sub_123',
                customerId: 'cus_123',
                paymentIntentId: $piId,
                chargeId: $chargeId,
                amountPaid: 15000,
            );

            $this->postJson('/stripe/webhook', $again)->assertOk();

            $this->assertSame(1, Transaction::count());
            $placeholder->refresh();
            $this->assertSame('succeeded', $placeholder->status);
            $this->assertSame($invoiceId, $placeholder->stripe_invoice_id);
        }
    }

    /**
     * Build an event payload (array) starting from a fixture file name.
     *
     * Supported:
     *  - invoice.paid
     *  - invoice.payment_succeeded (alias to invoice.paid fixture)
     *  - invoice_payment.paid
     *  - payment_intent.succeeded
     *  - charge.succeeded
     */
    private function buildEventFromFixtureName(
        string $fixtureKey,
        string $eventId,
        string $invoiceId,
        string $subscriptionId,
        string $customerId,
        string $paymentIntentId,
        string $chargeId,
        int $amountPaid,
    ): array {
        // Map "friendly keys" to your actual fixture filenames
        $map = [
            'invoice.paid'               => 'invoice.paid.json',
            'invoice.payment_succeeded'  => 'invoice.paid.json',           // same shape is fine for our handler
            'invoice_payment.paid'       => 'invoice_payment.paid.json',
            'payment_intent.succeeded'   => 'payment_intent.succeeded.json',
            'charge.succeeded'           => 'charge.succeeded.json',
        ];

        if (! isset($map[$fixtureKey])) {
            throw new \InvalidArgumentException("Unknown fixture key: {$fixtureKey}");
        }

        $obj = StripeEventFixture::load($map[$fixtureKey]);

        // Convert object -> array for postJson
        $event = json_decode(json_encode($obj, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        // Force event envelope id/type
        $event['id'] = $eventId;
        $event['type'] = $fixtureKey;

        // Normalize the data.object we care about, depending on event type
        $dataObject = $event['data']['object'] ?? [];

        if ($fixtureKey === 'invoice_payment.paid') {
            // Basil invoice_payment.paid carries invoice + payment.payment_intent :contentReference[oaicite:2]{index=2}
            $dataObject['invoice'] = $invoiceId;
            $dataObject['payment']['payment_intent'] = $paymentIntentId;

            $event['data']['object'] = $dataObject;
            return $event;
        }

        if (str_starts_with($fixtureKey, 'invoice.')) {
            // Invoice is canonical writer; your handler reads these keys.
            $dataObject['id'] = $invoiceId;
            $dataObject['customer'] = $customerId;
            $dataObject['subscription'] = $subscriptionId;
            $dataObject['payment_intent'] = $paymentIntentId;
            $dataObject['charge'] = $chargeId;
            $dataObject['currency'] = 'usd';
            $dataObject['amount_paid'] = $amountPaid;
            $dataObject['billing_reason'] = 'subscription_create';
            $dataObject['status_transitions']['paid_at'] = now()->timestamp;
            $dataObject['hosted_invoice_url'] = 'https://example.test/invoice/' . $invoiceId;
            $dataObject['customer_email'] = 'donor@example.test';
            $dataObject['customer_name'] = 'Donor Person';

            $event['data']['object'] = $dataObject;
            return $event;
        }

        if ($fixtureKey === 'payment_intent.succeeded') {
            // Ensure your PI handler sees it as subscription-related so it ignores it:
            // it checks $pi->invoice or invoice.id.
            $dataObject['id'] = $paymentIntentId;
            $dataObject['invoice'] = $invoiceId;
            $dataObject['customer'] = $customerId;
            $dataObject['latest_charge'] = $chargeId;

            $event['data']['object'] = $dataObject;
            return $event;
        }

        if ($fixtureKey === 'charge.succeeded') {
            // Ensure your Charge handler sees it as subscription-related so it ignores it:
            // it checks $charge->invoice or invoice.id.
            $dataObject['id'] = $chargeId;
            $dataObject['payment_intent'] = $paymentIntentId;
            $dataObject['customer'] = $customerId;
            $dataObject['invoice'] = $invoiceId;

            $event['data']['object'] = $dataObject;
            return $event;
        }

        // Default fallback (shouldn't hit)
        $event['data']['object'] = $dataObject;
        return $event;
    }
}
