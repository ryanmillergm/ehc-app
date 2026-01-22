<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Invoice;
use Tests\TestCase;

class StripePaymentIntentConvergenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_converges_on_the_existing_payment_intent_owner_and_never_violates_unique_constraints(): void
    {
        /**
         * This test recreates the production failure:
         *
         * - tx #1 already owns payment_intent_id = pi_x
         * - tx #2 (newer) is a placeholder with null PI
         * - sync attempts to apply PI again
         *
         * Expected: sync MUST switch to tx #1 (owner) and NOT crash trying to write PI onto tx #2.
         */

        $attemptId = 'attempt-test-123';

$pledge = Pledge::factory()->create([
    'attempt_id' => $attemptId,
    'amount_cents' => 200,
    'currency' => 'usd',
    'interval' => 'month',

    // ✅ ensure NOT NULL in your schema
    'status' => 'pending',

    // ensures sync path
    'stripe_subscription_id' => 'sub_test_123',
    'stripe_customer_id' => 'cus_test_123',
    'stripe_price_id' => 'price_test_123',
]);


        // Existing owner transaction (already has PI) — this is the row we must converge onto.
        $ownerTx = Transaction::factory()->create([
            'pledge_id' => $pledge->id,
            'attempt_id' => $attemptId,
            'type' => 'subscription_initial',
            'source' => 'donation_widget',
            'status' => 'pending',
            'payment_intent_id' => 'pi_test_123',
            'charge_id' => null,
        ]);

        // Newer placeholder transaction (null PI) — this is what old "latest()" logic would wrongly grab.
        $placeholderTx = Transaction::factory()->create([
            'pledge_id' => $pledge->id,
            'attempt_id' => $attemptId,
            'type' => 'subscription_initial',
            'source' => 'donation_widget',
            'status' => 'pending',
            'payment_intent_id' => null,
            'charge_id' => null,
        ]);

        // Fake Stripe subscription + expanded latest invoice data.
        $invoice = Invoice::constructFrom([
            'id' => 'in_test_123',
            'customer' => 'cus_test_123',
            'status' => 'paid',
            'payment_intent' => 'pi_test_123',
            'charge' => 'ch_test_123',
            'billing_reason' => 'subscription_create',
        ], null);

$subscription = Subscription::constructFrom([
    'id' => 'sub_test_123',
    'customer' => 'cus_test_123',

    // ✅ this is the missing piece: StripeService likely maps this to pledge.status
    'status' => 'active',
    'cancel_at_period_end' => false,
    'canceled_at' => null,

    'latest_invoice' => $invoice,
], null);


        // Mock StripeClient so StripeService never calls the network.
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

        // Important: leave paymentIntents client absent so guard soft-allows in unit tests.
        // (Your production client has it; the code already supports soft-allow when missing.)

        $service = new StripeService($stripe);

        // If your fix is working, this call will NOT throw UniqueConstraintViolationException.
        $service->createSubscriptionForPledge($pledge->fresh(), 'pm_test_123');

        // ✅ Assert: the PI remains owned by the original transaction
$this->assertDatabaseHas('transactions', [
    'id' => $ownerTx->id,
    'payment_intent_id' => 'pi_test_123',
    'subscription_id' => 'sub_test_123',
    'charge_id' => 'ch_test_123',
]);

// Optional: if your service *should* set receipt_url/paid_at here, assert those instead.

$this->assertSame(
    1,
    Transaction::query()->where('payment_intent_id', 'pi_test_123')->count(),
    'Exactly one transaction must own a given payment_intent_id.'
);


        // ✅ Assert: placeholder did NOT get the PI
        $this->assertDatabaseHas('transactions', [
            'id' => $placeholderTx->id,
            'payment_intent_id' => null,
        ]);

        // ✅ Assert: only one row in DB owns that PI
        $this->assertSame(
            1,
            Transaction::query()->where('payment_intent_id', 'pi_test_123')->count(),
            'Exactly one transaction must own a given payment_intent_id.'
        );
    }
}
