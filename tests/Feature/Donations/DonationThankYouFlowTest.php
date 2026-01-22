<?php

namespace Tests\Feature\Donations;

use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Charge;
use Stripe\PaymentIntent;
use Tests\TestCase;

class DonationThankYouFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent container boot from exploding if anything tries to read Stripe config.
        config()->set('services.stripe.secret', 'sk_test_dummy');
        config()->set('services.stripe.debug_state', false);
    }

    #[Test]
    public function json_complete_sets_session_redirects_to_session_only_thankyou_and_thankyou_is_one_time(): void
    {
        $tx = Transaction::factory()->create([
            'type'              => 'one_time',
            'status'            => 'requires_payment_method',
            'payment_intent_id' => 'pi_test_123',
            'source'            => 'donation_widget',
            'amount_cents'      => 2500,
            'currency'          => 'usd',
        ]);

        // Mock the app-level StripeService (your controller calls $this->stripe->retrievePaymentIntent()).
        $stripe = Mockery::mock(StripeService::class);

        // IMPORTANT: return real Stripe SDK objects, not stdClass.
        $pi = PaymentIntent::constructFrom([
            'id'            => 'pi_test_123',
            'status'        => 'succeeded',
            'latest_charge' => 'ch_test_123',
        ], null);

        $charge = Charge::constructFrom([
            'id'            => 'ch_test_123',
            'customer'      => 'cus_test_123',
            'payment_method'=> 'pm_test_123',
            'amount'        => 2500,
            'currency'      => 'usd',
            'receipt_url'   => 'https://example.test/receipt/ch_test_123',
            'billing_details' => [
                'email' => 'donor@example.test',
                'name'  => 'Test Donor',
            ],
            'payment_method_details' => [
                'card' => [
                    'brand'     => 'visa',
                    'last4'     => '4242',
                    'exp_month' => 12,
                    'exp_year'  => 2030,
                    'country'   => 'US',
                    'funding'   => 'credit',
                ],
            ],
        ], null);

        $stripe->shouldReceive('retrievePaymentIntent')
            ->once()
            ->with('pi_test_123')
            ->andReturn($pi);

        $stripe->shouldReceive('retrieveCharge')
            ->once()
            ->with('ch_test_123')
            ->andReturn($charge);

        // Your controller calls this as the final step in the JSON flow.
        $stripe->shouldReceive('finalizeTransactionFromPaymentIntent')
            ->once()
            ->withArgs(function ($transactionArg, $piArg) use ($tx) {
                return (int) $transactionArg->id === (int) $tx->id
                    && ($piArg instanceof PaymentIntent)
                    && ($piArg->id === 'pi_test_123');
            })
            ->andReturnUsing(function ($transactionArg, $piArg) {
                // Return the transaction to satisfy the return type.
                return $transactionArg;
            });

        $this->app->instance(StripeService::class, $stripe);

        // 1) Widget calls /donations/complete as JSON
        $res = $this->postJson(route('donations.complete'), [
            'mode'              => 'payment',
            'transaction_id'    => $tx->id,
            'attempt_id'        => 'attempt_test_123',
            'payment_intent_id' => 'pi_test_123',
            'donor_first_name'  => 'Test',
            'donor_last_name'   => 'Donor',
            'donor_email'       => 'donor@example.test',
        ]);

        $res->assertOk();
        $res->assertJsonPath('ok', true);

        $redirect = $res->json('redirect');
        $this->assertNotEmpty($redirect, 'Expected complete() to return a redirect URL.');

        // CRITICAL: redirect must be session-only thank you (no attempt_id leak)
        $this->assertSame(route('donations.thankyou'), $redirect);

        // 2) Follow redirect in same session
        $page = $this->get($redirect);
        $page->assertOk();

        // 3) Second hit must 404 because thankYou() uses session()->pull()
        $page2 = $this->get($redirect);
        $page2->assertNotFound();

        // DB sanity
        $tx->refresh();
        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);
    }

    #[Test]
    public function thankyou_requires_session_and_does_not_allow_attempt_id_access(): void
    {
        $tx = Transaction::factory()->create([
            'attempt_id' => 'attempt_private_abc',
            'type'       => 'one_time',
            'status'     => 'succeeded',
        ]);

        // No session => 404
        $this->get(route('donations.thankyou'))->assertNotFound();

        // Even if someone copies an attempt_id URL, it must NOT load.
        $this->get(route('donations.thankyou', ['attempt_id' => $tx->attempt_id]))
            ->assertNotFound();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
