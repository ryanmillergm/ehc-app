<?php

namespace Tests\Feature\Stripe;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\Subscription;
use Tests\TestCase;

/**
 * Regression test: reproduce the production failure pattern where the webhook has already
 * created the canonical (pledge_id, stripe_invoice_id) transaction, and the user-facing
 * completion endpoint tries to set stripe_invoice_id on a pending placeholder transaction.
 *
 * Expected behavior:
 * - do NOT 500
 * - do NOT let the placeholder "steal" the canonical invoice id
 * - the canonical tx remains the owner of (pledge_id, stripe_invoice_id = in_123)
 */
class DonationCompleteAdoptsCanonicalInvoiceTransactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function complete_subscription_does_not_500_when_invoice_already_owned_by_another_tx(): void
    {
        // Arrange: pledge with an attempt id (simulates the browser checkout flow)
        $pledge = Pledge::factory()->create([
            'attempt_id'   => (string) Str::uuid(),
            'amount_cents' => 15000,
            'currency'     => 'usd',
            'status'       => 'pending',
            'donor_email'  => 'donor@example.com',
            'donor_name'   => 'Test Donor',
        ]);

        // Placeholder created by donation widget "start" flow (or earlier completion attempt)
        $pending = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'type'              => 'subscription_initial',
            'status'            => 'pending',
            'source'            => 'donation_widget',
            'stripe_invoice_id' => null,
            'payment_intent_id' => null,
            'charge_id'         => null,
            'amount_cents'      => $pledge->amount_cents,
            'currency'          => $pledge->currency,
            'payer_email'       => $pledge->donor_email,
            'payer_name'        => $pledge->donor_name,
        ]);

        // Canonical transaction already created by webhook and owns the invoice id
        $canonical = Transaction::factory()->create([
            'pledge_id'         => $pledge->id,
            'attempt_id'        => $pledge->attempt_id,
            'type'              => 'subscription_initial',
            'status'            => 'succeeded',
            'source'            => 'stripe_webhook',
            'stripe_invoice_id' => 'in_123',
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'amount_cents'      => $pledge->amount_cents,
            'currency'          => $pledge->currency,
            'payer_email'       => $pledge->donor_email,
            'payer_name'        => $pledge->donor_name,
        ]);

        // Mock StripeService so DonationsController@complete doesn't call real Stripe.
        $this->mock(StripeService::class, function ($mock) {
            $pi = PaymentIntent::constructFrom([
                'id'            => 'pi_123',
                'latest_charge' => 'ch_123',
            ]);

            $invoice = Invoice::constructFrom([
                'id'            => 'in_123',
                'payment_intent' => $pi,
                'charge'         => 'ch_123',
            ]);

            $sub = Subscription::constructFrom([
                'id'            => 'sub_123',
                'status'        => 'active',
                'latest_invoice' => $invoice,
            ]);

            $mock->shouldReceive('createSubscriptionForPledge')
                ->once()
                ->andReturn($sub);

            // Your controller may call this during JSON flows; harmless to allow.
            $mock->shouldReceive('retrievePaymentIntent')
                ->zeroOrMoreTimes()
                ->andReturn($pi);

            // Some implementations call sync; allow it if present.
            $mock->shouldReceive('syncFromSubscription')
                ->zeroOrMoreTimes()
                ->andReturnNull();
        });

        // Act
        $response = $this->postJson(route('donations.complete'), [
            'mode'              => 'subscription',
            'pledge_id'         => $pledge->id,
            'payment_method_id' => 'pm_123',
        ]);

        // Assert: request succeeds and returns redirect payload
        $response->assertOk();
        $response->assertJsonStructure(['redirect']);

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('redirect', $json);
        $this->assertNotEmpty($json['redirect']);

        // Assert: the placeholder did NOT steal the canonical invoice id.
        // (It may legitimately get some other invoice id, depending on extraction paths.)
        $pending->refresh();
        $this->assertNotSame('in_123', $pending->stripe_invoice_id);

        $this->assertDatabaseMissing('transactions', [
            'id'               => $pending->id,
            'stripe_invoice_id' => 'in_123',
        ]);

        // Assert: the canonical transaction remains the owner.
        $canonical->refresh();
        $this->assertSame('in_123', $canonical->stripe_invoice_id);

        $this->assertDatabaseHas('transactions', [
            'id'               => $canonical->id,
            'stripe_invoice_id' => 'in_123',
        ]);

        // Optional sanity: uniqueness is preserved (exactly one owner for pledge+invoice)
        $this->assertSame(
            1,
            Transaction::query()
                ->where('pledge_id', $pledge->id)
                ->where('stripe_invoice_id', 'in_123')
                ->count()
        );
    }
}
