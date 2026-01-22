<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookReplayIsIdempotentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function posting_the_same_payment_intent_succeeded_payload_twice_does_not_create_duplicate_transactions(): void
    {
        // Some apps read webhook secret from different config keys.
        // Set the common ones so the test is robust.
        config([
            'services.stripe.secret' => 'sk_test_123',

            'services.stripe.webhook_secret'         => 'whsec_test_123',
            'services.stripe.webhook_signing_secret' => 'whsec_test_123',
        ]);

        $pledge = Pledge::factory()->create([
            'stripe_customer_id'     => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
            'status'                 => 'active',
        ]);

        // Canonical row already exists (e.g. created by invoice.paid path)
        $tx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'user_id'           => $pledge->user_id,
            'payment_intent_id' => 'pi_test_123',
            'status'            => 'pending',
            'type'              => 'subscription_initial',
            'source'            => 'donation_widget',
        ]);

        $payload = $this->stripeEventPayload('payment_intent.succeeded', [
            'id'            => 'pi_test_123',
            'status'        => 'succeeded',
            'customer'      => $pledge->stripe_customer_id,
            'latest_charge' => 'ch_test_123',
        ]);

        // First delivery.
        $this->postStripeWebhookRaw($payload)->assertOk();

        // Replay delivery.
        $this->postStripeWebhookRaw($payload)->assertOk();

        $this->assertSame(
            1,
            Transaction::query()->where('payment_intent_id', 'pi_test_123')->count()
        );

        $tx->refresh();
        $this->assertSame('ch_test_123', $tx->charge_id);
        $this->assertSame('succeeded', $tx->status);
    }

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
     * IMPORTANT: send the raw body exactly as signed (do NOT use postJson()).
     */
    protected function postStripeWebhookRaw(string $payload)
    {
        $secret = (string) (
            config('services.stripe.webhook_secret')
            ?: config('services.stripe.webhook_signing_secret')
            ?: ''
        );

        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        $sigHeader = "t={$timestamp},v1={$signature}";

        return $this->call(
            'POST',
            '/stripe/webhook',
            [],     // parameters
            [],     // cookies
            [],     // files
            [       // server
                'CONTENT_TYPE'          => 'application/json',
                'HTTP_Stripe-Signature' => $sigHeader,
            ],
            $payload // raw content
        );
    }
}
