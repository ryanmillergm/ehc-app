<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Service\SubscriptionService;
use Stripe\Service\InvoiceService;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;
use Stripe\Subscription;
use Tests\TestCase;

class PaymentIntentOwnerGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_refuses_to_attach_a_payment_intent_when_the_pi_customer_does_not_match_the_pledge_customer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-02 00:00:00'));

        // Local pledge says it belongs to cus_EXPECTED
        $pledge = Pledge::factory()->create([
            'status'                   => 'active',
            'currency'                 => 'usd',
            'interval'                 => 'month',
            'amount_cents'             => 200,
            'stripe_customer_id'       => 'cus_EXPECTED',
            'stripe_subscription_id'   => 'sub_test_guard',
            'latest_payment_intent_id' => null,
        ]);

        // Subscription's latest_invoice includes a PI id.
        $invoice = Invoice::constructFrom([
            'id' => 'in_guard_123',
            'paid' => true,
            'status_transitions' => [
                'paid_at' => Carbon::now()->timestamp,
            ],
            'payment_intent' => 'pi_mismatch_123',
            'charge' => 'ch_should_be_refused',
            'customer' => 'cus_EXPECTED',
            'amount_paid' => 200,
            'currency' => 'usd',
            'lines' => [
                'data' => [
                    [
                        'period' => [
                            'start' => Carbon::now()->subDay()->timestamp,
                            'end'   => Carbon::now()->addMonth()->timestamp,
                        ],
                    ],
                ],
            ],
        ]);

        $subscription = Subscription::constructFrom([
            'id' => 'sub_test_guard',
            'status' => 'active',
            'customer' => 'cus_EXPECTED',
            'latest_invoice' => $invoice,
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test_1',
                        'current_period_start' => Carbon::now()->subDay()->timestamp,
                        'current_period_end'   => Carbon::now()->addMonth()->timestamp,
                    ],
                ],
            ],
        ]);

        // Create mock services
        $subsService = Mockery::mock(SubscriptionService::class);
        $invService  = Mockery::mock(InvoiceService::class);
        $piService   = Mockery::mock(PaymentIntentService::class);

        // Mock StripeClient with getService support
        // Use makePartial() to allow property access while mocking specific methods
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        
        // Set the service properties directly so isset() and is_object() work
        $stripe->paymentIntents = $piService;
        $stripe->subscriptions = $subsService;
        $stripe->invoices = $invService;
        
        // Mock getService calls that StripeClient uses internally
        $stripe->shouldReceive('getService')
            ->with('subscriptions')
            ->andReturn($subsService);
        
        $stripe->shouldReceive('getService')
            ->with('invoices')
            ->andReturn($invService);
        
        $stripe->shouldReceive('getService')
            ->with('paymentIntents')
            ->andReturn($piService);

        // In "existing subscription id" branch, we retrieve the subscription.
        $subsService->shouldReceive('retrieve')
            ->once()
            ->with('sub_test_guard', Mockery::type('array'))
            ->andReturn($subscription);

        // Not expected, but allowed as future-proof fallback.
        $invService->shouldReceive('retrieve')
            ->zeroOrMoreTimes()
            ->andReturn($invoice);

        // Owner guard retrieves PI and sees customer mismatch.
        $pi = PaymentIntent::constructFrom([
            'id' => 'pi_mismatch_123',
            'customer' => Customer::constructFrom(['id' => 'cus_SOMEONE_ELSE']),
            'latest_charge' => 'ch_from_pi_should_be_refused',
        ]);

        $piService->shouldReceive('retrieve')
            ->once()
            ->with('pi_mismatch_123', [])
            ->andReturn($pi);

        $service = new StripeService($stripe);

        $service->createSubscriptionForPledge($pledge->refresh(), 'pm_test_2');

        $pledge->refresh();

        // ✅ Must NOT attach PI when mismatch
        $this->assertNull($pledge->latest_payment_intent_id);

        // Transaction (if created) must not claim PI/charge either.
        $tx = Transaction::query()
            ->where('pledge_id', $pledge->id)
            ->whereIn('type', ['subscription_initial', 'subscription_recurring'])
            ->first();

        if ($tx) {
            $this->assertEmpty($tx->payment_intent_id, 'Transaction should not claim PI when owner guard fails');
            $this->assertEmpty($tx->charge_id, 'Transaction should not claim charge when owner guard fails');
        }
    }
}