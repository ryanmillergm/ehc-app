<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Tests\TestCase;

class SubscriptionCreationEnrichesTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_donation_creates_single_transaction_and_enriches_with_ids(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

$pledge = Pledge::factory()->create([
    'user_id' => $user->id,
    'attempt_id' => 'attempt_test_123',
    'amount_cents' => 600,
    'currency' => 'usd',
    'interval' => 'month',
    'status' => 'incomplete',

    // 👇 important: ensure nothing is pre-populated by the factory
    'stripe_customer_id' => null,
    'stripe_subscription_id' => null,
    'stripe_price_id' => null,
    'setup_intent_id' => null,
    'latest_invoice_id' => null,
    'latest_payment_intent_id' => null,
]);


        $stripe = Mockery::mock(StripeService::class);
        $this->app->instance(StripeService::class, $stripe);

        $pi = PaymentIntent::constructFrom([
            'id' => 'pi_test_123',
            'latest_charge' => 'ch_test_123',
        ]);

        $invoice = Invoice::constructFrom([
            'id' => 'in_test_123',
            'hosted_invoice_url' => 'https://invoice.test/in_test_123',
            'payment_intent' => $pi,
            'charge' => 'ch_test_123',
        ]);

        $subscription = Subscription::constructFrom([
            'id' => 'sub_test_123',
            'status' => 'active',
            'latest_invoice' => $invoice,
            'customer' => 'cus_test_123',
            'default_payment_method' => 'pm_test_123',
        ]);

        $stripe->shouldReceive('createSubscriptionForPledge')
            ->once()
            ->withArgs(function ($p, $pmId) use ($pledge) {
                return (int) $p->id === (int) $pledge->id && $pmId === 'pm_test_123';
            })
            ->andReturn($subscription);

        $this->post(route('donations.complete'), [
            'mode' => 'subscription',
            'attempt_id' => 'attempt_test_123',
            'pledge_id' => $pledge->id,
            'payment_method_id' => 'pm_test_123',
        ])->assertStatus(302);

        $this->assertSame(1, Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->where('type', 'subscription_initial')
            ->count()
        );

        $tx = Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->where('type', 'subscription_initial')
            ->firstOrFail();

        $this->assertSame('sub_test_123', $tx->subscription_id);
        $this->assertSame('in_test_123', $tx->stripe_invoice_id);
        $this->assertSame('pi_test_123', $tx->payment_intent_id);
        $this->assertSame('ch_test_123', $tx->charge_id);
        $this->assertSame('https://invoice.test/in_test_123', $tx->receipt_url);
    }
}
