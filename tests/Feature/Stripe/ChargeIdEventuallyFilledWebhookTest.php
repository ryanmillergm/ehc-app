<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves a real-world Stripe behavior:
 * - invoice.paid can arrive without a charge id
 * - payment_intent.succeeded arrives later and carries latest_charge
 * - our webhook handling must converge and backfill charge_id on the existing transaction
 */
class ChargeIdEventuallyFilledWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_paid_then_payment_intent_succeeded_eventually_fills_charge_id(): void
    {
        // Ensure webhook signature verification can run in tests.
        config([
            'services.stripe.webhook_secret' => 'whsec_test_123',
            'services.stripe.secret'         => 'sk_test_123',
        ]);

        $pledge = Pledge::factory()->create([
            'stripe_customer_id'     => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'status'                 => 'active',
        ]);

        // 1) invoice.paid arrives first. It references the PI but may not include a charge id.
        $invoicePayload = $this->stripeEventPayload('invoice.paid', [
            'id'            => 'in_test_123',
            'customer'      => $pledge->stripe_customer_id,
            'subscription'  => $pledge->stripe_subscription_id,
            'paid'          => true,
            'status'        => 'paid',
            'payment_intent'=> 'pi_test_123',
            'amount_paid'   => 1000,
            'currency'      => 'usd',
            'lines'         => [
                'data' => [
                    [
                        'period' => [
                            'start' => now()->subMinute()->timestamp,
                            'end'   => now()->addMonth()->timestamp,
                        ],
                    ],
                ],
            ],
        ]);

        $this->postStripeWebhook($invoicePayload)
            ->assertOk();

        $tx = Transaction::where('payment_intent_id', 'pi_test_123')->firstOrFail();
        $this->assertSame($pledge->id, $tx->pledge_id);
        $this->assertNull($tx->charge_id, 'invoice.paid should not magically invent a charge id');

        // 2) Later, Stripe sends payment_intent.succeeded which includes latest_charge.
        $piPayload = $this->stripeEventPayload('payment_intent.succeeded', [
            'id'            => 'pi_test_123',
            'status'        => 'succeeded',
            'customer'      => $pledge->stripe_customer_id,
            'latest_charge' => 'ch_test_123',
        ]);

        $this->postStripeWebhook($piPayload)
            ->assertOk();

        $tx->refresh();
        $this->assertSame('ch_test_123', $tx->charge_id, 'charge_id should be backfilled from PI.latest_charge');
    }

    /**
     * Build a Stripe-ish event envelope.
     */
    protected function stripeEventPayload(string $type, array $object): string
    {
        return json_encode([
            'id'   => 'evt_' . $type . '_test',
            'type' => $type,
            'data' => [
                'object' => $object,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Send the webhook request with a valid Stripe-Signature header.
     */
    protected function postStripeWebhook(string $payload)
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $timestamp = time();

        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        $sigHeader = 't=' . $timestamp . ',v1=' . $signature;

        return $this->call(
            'POST',
            '/stripe/webhook',
            [],     // parameters
            [],     // cookies
            [],     // files
            [       // server
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $sigHeader,
            ],
            $payload // raw content (THIS is what Request::getContent() reads)
        );
    }
}
