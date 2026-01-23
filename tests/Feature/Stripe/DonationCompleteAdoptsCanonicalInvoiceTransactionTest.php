<?php

namespace Tests\Feature\Stripe;

use App\Http\Controllers\Donations\DonationsController;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression test: reproduce the production failure pattern where the webhook has already
 * created the canonical (pledge_id, stripe_invoice_id) transaction, and the user-facing
 * completion endpoint tries to set stripe_invoice_id on a pending placeholder transaction.
 *
 * Expected behavior: do NOT 500; adopt the canonical tx; return success/redirect.
 */
class DonationCompleteAdoptsCanonicalInvoiceTransactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function complete_subscription_does_not_500_when_invoice_already_owned_by_another_tx(): void
    {
        // Arrange: a pledge with a pending placeholder transaction created at checkout start.
        $pledge = Pledge::factory()->create([
            'attempt_id'     => (string) Str::uuid(),
            'amount_cents'   => 15000,
            'currency'       => 'usd',
            'status'         => 'pending',
            'donor_email'    => 'donor@example.com',
            'donor_name'     => 'Test Donor',
        ]);

        $pending = Transaction::factory()->create([
            'pledge_id'          => $pledge->id,
            'attempt_id'         => $pledge->attempt_id,
            'type'               => 'subscription_initial',
            'status'             => 'pending',
            'source'             => 'donation_widget',
            'stripe_invoice_id'  => null,
            'payment_intent_id'  => null,
            'charge_id'          => null,
            'amount_cents'       => $pledge->amount_cents,
            'currency'           => $pledge->currency,
            'payer_email'        => $pledge->donor_email,
            'payer_name'         => $pledge->donor_name,
        ]);

        // And: webhook already created the canonical tx owning (pledge_id, stripe_invoice_id)
        $canonical = Transaction::factory()->create([
            'pledge_id'          => $pledge->id,
            'attempt_id'         => $pledge->attempt_id,
            'type'               => 'subscription_initial',
            'status'             => 'succeeded',
            'source'             => 'stripe_webhook',
            'stripe_invoice_id'  => 'in_123',
            'payment_intent_id'  => 'pi_123',
            'charge_id'          => 'ch_123',
            'amount_cents'       => $pledge->amount_cents,
            'currency'           => $pledge->currency,
            'payer_email'        => $pledge->donor_email,
            'payer_name'         => $pledge->donor_name,
        ]);

        // Mock StripeService so DonationsController@complete doesn't call Stripe.
        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createSubscriptionForPledge')
                ->andReturn((object) [
                    'id'            => 'sub_123',
                    'status'        => 'active',
                    'latest_invoice'=> (object) [
                        'id'            => 'in_123',
                        'payment_intent'=> (object) ['id' => 'pi_123'],
                        'charge'        => 'ch_123',
                    ],
                ]);

            // Some implementations attempt to retrieve PI for charge fallback. Keep it safe.
            $mock->shouldReceive('retrievePaymentIntent')
                ->andReturn((object) [
                    'id'           => 'pi_123',
                    'latest_charge'=> 'ch_123',
                ]);
        });

        $payload = [
            'mode'              => 'subscription',
            'pledge_id'         => $pledge->id,
            'payment_method_id' => 'pm_123',
        ];

        $request = Request::create('/donations/complete', 'POST', $payload, [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Provide a real session so the controller can flash/log without blowing up.
        $session = $this->app['session']->driver();
        $session->start();
        $request->setLaravelSession($session);

        // Act: call the controller action directly (no need to guess route names).
        $controller = $this->app->make(DonationsController::class);

        $response = $controller->complete($request);

        // Assert: we got a successful response (no 500 / no exception)
        $this->assertTrue(method_exists($response, 'getStatusCode'));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $json = $response->getData(true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('redirect', $json);
        $this->assertNotEmpty($json['redirect']);

        // Pending tx should NOT have claimed the invoice.
        $pending->refresh();
        $this->assertNull($pending->stripe_invoice_id);

        // Canonical tx remains the owner.
        $canonical->refresh();
        $this->assertSame('in_123', $canonical->stripe_invoice_id);
    }
}
