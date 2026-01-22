<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\StripeWebhookController;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class StripePaymentIntentConvergenceWithWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_converges_on_pi_owner_and_webhook_finalizes_without_unique_collisions(): void
    {
        // ----------------------------
        // Arrange (same collision setup)
        // ----------------------------
        $attemptId = 'attempt-test-123';

        $pledge = Pledge::factory()->create([
            'attempt_id' => $attemptId,

            // Ensure NOT NULL for your schema
            'status' => 'pending',

            // Ensure StripeService takes the "subscription sync" path
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_customer_id'     => 'cus_test_123',
            'stripe_price_id'        => 'price_test_123',

            'amount_cents' => 200,
            'currency'     => 'usd',
            'interval'     => 'month',
        ]);

        // Existing tx that already owns the PI (the one we MUST converge onto)
        $ownerTx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $attemptId,
            'type'              => 'subscription_initial',
            'source'            => 'donation_widget',
            'status'            => 'pending',
            'payment_intent_id' => 'pi_test_123',
            'charge_id'         => null,
            'subscription_id'   => 'sub_test_123',
        ]);

        // Newer placeholder tx (null PI) – this is what "latest()" used to pick incorrectly
        $placeholderTx = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $attemptId,
            'type'              => 'subscription_initial',
            'source'            => 'donation_widget',
            'status'            => 'pending',
            'payment_intent_id' => null,
            'charge_id'         => null,
            'subscription_id'   => 'sub_test_123',
        ]);

        // ----------------------------
        // Step 1: run the sync path (realistic)
        // ----------------------------
        $invoice = Invoice::constructFrom([
            'id' => 'in_test_123',
            'customer' => 'cus_test_123',
            'status' => 'paid',
            'billing_reason' => 'subscription_create',

            // These matter for your sync logic
            'payment_intent' => 'pi_test_123',
            'charge' => 'ch_test_123',

            // Optional but helps downstream logic
            'hosted_invoice_url' => 'https://example.test/invoice/in_test_123',
            'amount_paid' => 200,
            'currency' => 'usd',
        ], null);

        $subscription = Subscription::constructFrom([
            'id' => 'sub_test_123',
            'customer' => 'cus_test_123',

            // CRITICAL: your sync sets pledge->status = $subscription->status (NOT NULL)
            'status' => 'active',

            'latest_invoice' => $invoice,
        ], null);

        /** @var \Mockery\MockInterface&\Stripe\StripeClient $stripe */
        $stripe = Mockery::mock(StripeClient::class);

        // StripeService uses: $this->stripe->subscriptions->retrieve(...)
        $stripe->subscriptions = new class($subscription) {
            public function __construct(private Subscription $subscription) {}
            public function retrieve($id, $opts = [])
            {
                return $this->subscription;
            }
        };

        $service = new StripeService($stripe);

        // Must not throw and must not violate unique constraints
        $service->createSubscriptionForPledge($pledge->fresh(), 'pm_test_123');

        // Still exactly one PI owner after sync
        $this->assertSame(1, Transaction::where('payment_intent_id', 'pi_test_123')->count());

        // Placeholder still does NOT have PI
        $this->assertDatabaseHas('transactions', [
            'id' => $placeholderTx->id,
            'payment_intent_id' => null,
        ]);

        // ----------------------------
        // Step 2: simulate real webhook finalization via StripeWebhookController
        // ----------------------------
        // The controller requires either:
        // - a webhook secret + valid Stripe signature, OR
        // - local env with no secret (it json_decodes payload)
        $this->app['env'] = 'local';
        config([
            'services.stripe.webhook_secret' => null,
            'services.stripe.debug_state' => true,
        ]);

        $paidAt = now()->timestamp;

        $eventPayload = [
            'id'   => 'evt_test_123',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_test_123',
                    'object' => 'invoice',

                    'customer'     => 'cus_test_123',
                    'subscription' => 'sub_test_123',

                    'payment_intent' => 'pi_test_123',
                    'charge'         => 'ch_test_123',

                    'billing_reason' => 'subscription_create',
                    'amount_paid'    => 200,
                    'currency'       => 'usd',

                    'hosted_invoice_url' => 'https://example.test/invoice/in_test_123',
                    'customer_email'     => 'ryan@example.test',

                    // Used by pledge period/paid_at calculations
                    'status_transitions' => [
                        'paid_at' => $paidAt,
                    ],
                    'lines' => [
                        'data' => [
                            [
                                'period' => [
                                    'start' => $paidAt,
                                    'end'   => $paidAt + (30 * 24 * 60 * 60),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create(
            '/stripe/webhook',
            'POST',
            [],
            [],
            [],
            [],
            json_encode($eventPayload, JSON_THROW_ON_ERROR)
        );

        // No signature needed in local env when webhook_secret is null
        $controller = app(StripeWebhookController::class);
        $response = $controller($request);

        $this->assertTrue($response->isOk());

        // ----------------------------
        // Assert: webhook finalized the *owner* tx and did NOT steal PI onto placeholder
        // ----------------------------
        $this->assertDatabaseHas('transactions', [
            'id' => $ownerTx->id,
            'payment_intent_id' => 'pi_test_123',
            'subscription_id' => 'sub_test_123',
            'charge_id' => 'ch_test_123',
            'status' => 'succeeded',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $placeholderTx->id,
            'payment_intent_id' => null,
        ]);

        $this->assertSame(
            1,
            Transaction::query()->where('payment_intent_id', 'pi_test_123')->count(),
            'Exactly one transaction must own a given payment_intent_id even after webhook finalization.'
        );
    }
}
