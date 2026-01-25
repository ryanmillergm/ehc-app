<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionCheckoutReconciliationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reconciles_invoice_paid_webhook_to_placeholder_transaction_and_is_idempotent(): void
    {
        // Arrange: what start(monthly) creates
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
            'pledge_id'        => $pledge->id,
            'attempt_id'       => $attemptId,
            'type'             => 'subscription_initial',
            'status'           => 'pending',
            'source'           => 'donation_widget',
            'amount_cents'     => 15000,
            'currency'         => 'usd',
            'subscription_id'  => 'sub_123',
            'setup_intent_id'  => 'seti_123',
            'stripe_invoice_id'=> null,
            'payment_intent_id'=> null,
            'charge_id'        => null,
        ]);

        $this->assertSame(1, Transaction::count());
        $this->assertSame('pending', $placeholder->status);

        // Build a Stripe-like "invoice.payment_succeeded" event payload.
        // Keep it minimal but include the fields your handler reads.
        $event = [
            'id'   => 'evt_test_1',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_123',
                    'customer' => 'cus_123',
                    'subscription' => 'sub_123',
                    'payment_intent' => 'pi_123',
                    'charge' => 'ch_123',
                    'currency' => 'usd',
                    'amount_paid' => 15000,
                    'billing_reason' => 'subscription_create',
                    'status_transitions' => ['paid_at' => now()->timestamp],
                    'hosted_invoice_url' => 'https://example.test/invoice/in_123',
                    'customer_email' => 'donor@example.test',
                    'customer_name' => 'Donor Person',
                ],
            ],
        ];

        // Act #1: hit the real webhook endpoint (production path)
        $res1 = $this->postJson('/stripe/webhook', $event);

        // Assert #1: webhook accepted
        $res1->assertOk();

        // Assert #1: still 1 tx, now succeeded and invoice-owned
        $this->assertSame(1, Transaction::count());

        $placeholder->refresh();

        $this->assertSame('succeeded', $placeholder->status);
        $this->assertSame('in_123', $placeholder->stripe_invoice_id);
        $this->assertSame('pi_123', $placeholder->payment_intent_id);
        $this->assertSame('ch_123', $placeholder->charge_id);
        $this->assertNotNull($placeholder->paid_at);

        // setup intent carried through
        $this->assertSame('seti_123', $placeholder->setup_intent_id);

        // Act #2: send the same event again (idempotency)
        $event['id'] = 'evt_test_2';
        $res2 = $this->postJson('/stripe/webhook', $event);

        $res2->assertOk();

        // Assert #2: no duplicates
        $this->assertSame(1, Transaction::count());

        $placeholder->refresh();
        $this->assertSame('succeeded', $placeholder->status);
        $this->assertSame('in_123', $placeholder->stripe_invoice_id);
        $this->assertSame('pi_123', $placeholder->payment_intent_id);
        $this->assertSame('ch_123', $placeholder->charge_id);
    }
}
