<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Tests\TestCase;

class SubscriptionCreationEnrichesTransactionTest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    public function test_monthly_donation_enriches_pledge_with_subscription_ids(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pledge = Pledge::factory()->create([
            'user_id'                  => $user->id,
            'attempt_id'               => 'attempt_test_123',
            'amount_cents'             => 600,
            'currency'                 => 'usd',
            'interval'                 => 'month',
            'status'                   => 'incomplete',

            // ensure nothing is pre-populated
            'stripe_customer_id'       => null,
            'stripe_subscription_id'   => null,
            'stripe_price_id'          => null,
            'setup_intent_id'          => null,
            'latest_invoice_id'        => null,
            'latest_payment_intent_id' => null,
        ]);

        // ✅ CRITICAL: create the placeholder ("anchor") transaction the controller requires.
        Transaction::factory()->create([
            'pledge_id'    => $pledge->id,
            'attempt_id'   => 'attempt_test_123',
            'type'         => 'subscription_initial',
            'source'       => 'donation_widget',
            'status'       => 'pending',
            'amount_cents' => 600,
            'currency'     => 'usd',
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $this->app->instance(StripeService::class, $stripe);

        $pi = PaymentIntent::constructFrom([
            'id'            => 'pi_test_123',
            'latest_charge' => 'ch_test_123',
        ]);

        $invoice = Invoice::constructFrom([
            'id'                 => 'in_test_123',
            'hosted_invoice_url' => 'https://invoice.test/in_test_123',
            'payment_intent'     => $pi,
            'charge'             => 'ch_test_123',
        ]);

        $subscription = Subscription::constructFrom([
            'id'                     => 'sub_test_123',
            'status'                 => 'active',
            'latest_invoice'         => $invoice,
            'customer'               => 'cus_test_123',
            'default_payment_method' => 'pm_test_123',
        ]);

        $stripe->shouldReceive('createSubscriptionForPledge')
            ->once()
            ->andReturn($subscription);

        // Optional: if your controller might call other StripeService methods in this codepath,
        // you can allow them without failing the test. (Keeps the test focused.)
        $stripe->shouldIgnoreMissing();

        $response = $this->post(route('donations.complete'), [
            'mode'              => 'subscription',
            'attempt_id'        => 'attempt_test_123',
            'pledge_id'         => $pledge->id,
            'payment_method_id' => 'pm_test_123',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $pledge->refresh();

        // ✅ Controller responsibility (even if tx is written later by webhook)
        $this->assertSame('cus_test_123', $pledge->stripe_customer_id);
        $this->assertSame('sub_test_123', $pledge->stripe_subscription_id);

        // If your code persists these:
        $this->assertSame('in_test_123', $pledge->latest_invoice_id);
        $this->assertSame('pi_test_123', $pledge->latest_payment_intent_id);
    }
}
