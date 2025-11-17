<?php

namespace Tests\Feature\Donations;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteDonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_updates_transaction_and_redirects(): void
    {
        // Prevent real Stripe client from being constructed
        $this->mock(StripeService::class);

        $tx = Transaction::factory()->create([
            'amount_cents' => 2500,
            'status'       => 'pending',
            'type'         => 'one_time',
        ]);

        $response = $this->post(route('donations.complete'), [
            'mode'              => 'payment',
            'transaction_id'    => $tx->id,
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'payment_method_id' => 'pm_123',
            'receipt_url'       => 'https://example.test/receipt',
        ]);

        $response->assertRedirect(route('donations.thankyou', $tx->id));

        $this->assertDatabaseHas('transactions', [
            'id'                => $tx->id,
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
        ]);
    }

    public function test_complete_subscription_updates_pledge_and_creates_transaction(): void
    {
        // Prevent real Stripe client from being constructed
        $this->mock(StripeService::class);

        // Minimal pledge row we can work with
        $pledge = Pledge::create([
            'user_id'     => null,
            'amount_cents'=> 1500,
            'currency'    => 'usd',
            'interval'    => 'month',
            'status'      => 'incomplete',
            'donor_email' => 'test@example.com',
            'donor_name'  => 'Test Donor',
            'metadata'    => [],
        ]);

        $response = $this->post(route('donations.complete'), [
            'mode'              => 'subscription',
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_123',
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'receipt_url'       => 'https://example.test/receipt',
        ]);

        $response->assertRedirect(
            route('donations.thankyou-subscription', $pledge->id)
        );

        // Pledge should be activated and linked to Stripe subscription
        $this->assertDatabaseHas('pledges', [
            'id'                       => $pledge->id,
            'status'                   => 'active',
            'stripe_subscription_id'   => 'sub_123',
            'latest_payment_intent_id' => 'pi_123',
        ]);

        // Initial transaction row for reporting
        $this->assertDatabaseHas('transactions', [
            'pledge_id'         => $pledge->id,
            'subscription_id'   => 'sub_123',
            'payment_intent_id' => 'pi_123',
            'amount_cents'      => 1500,
            'currency'          => 'usd',
            'type'              => 'subscription_initial',
            'status'            => 'succeeded',
        ]);
    }
}
