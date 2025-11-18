<?php

namespace Tests\Feature\Donations;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

class CompleteDonationTest extends TestCase
{
    use RefreshDatabase;

    /** @var \Mockery\MockInterface|StripeService */
    protected MockInterface $stripeMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a mock StripeService so the controller never hits real Stripe.
        $this->stripeMock = $this->mock(StripeService::class);
    }

    public function test_complete_updates_transaction_and_redirects(): void
    {
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

        $response->assertRedirect(route('donations.thankyou', $tx));

        $this->assertDatabaseHas('transactions', [
            'id'                => $tx->id,
            'status'            => 'succeeded',
            'payment_intent_id' => 'pi_123',
            'charge_id'         => 'ch_123',
            'payment_method_id' => 'pm_123',
            'receipt_url'       => 'https://example.test/receipt',
        ]);
    }

    public function test_complete_subscription_updates_pledge_donor_info_and_calls_stripe_service(): void
    {
        // Minimal pledge row we can work with
        $pledge = Pledge::create([
            'user_id'      => null,
            'amount_cents' => 1500,
            'currency'     => 'usd',
            'interval'     => 'month',
            'status'       => 'incomplete',
            'donor_email'  => 'old@example.test',
            'donor_name'   => 'Old Name',
            'metadata'     => [],
        ]);

        // Fake Stripe subscription instance to satisfy the return type
        $fakeStripeSubscription = \Mockery::mock(StripeSubscription::class);

        // Expect the controller to call StripeService::createSubscriptionForPledge()
        $this->stripeMock
            ->shouldReceive('createSubscriptionForPledge')
            ->once()
            ->withArgs(function (Pledge $argPledge, string $paymentMethodId) use ($pledge) {
                return $argPledge->is($pledge) &&
                    $paymentMethodId === 'pm_123';
            })
            ->andReturn($fakeStripeSubscription);

        $payload = [
            'mode'              => 'subscription',
            'pledge_id'         => $pledge->id,
            'payment_method_id' => 'pm_123', // required_if:mode,subscription
            'donor_first_name'  => 'Ryan',
            'donor_last_name'   => 'Miller',
            'donor_email'       => 'ryan@example.test',
        ];

        $response = $this->post(route('donations.complete'), $payload);

        // Controller should still send us to the subscription thank-you page
        $response->assertRedirect(
            route('donations.thankyou-subscription', $pledge)
        );

        // Pledge donor info updated from the payload;
        // subscription id / invoice / transactions are handled in StripeService + webhooks
        $this->assertDatabaseHas('pledges', [
            'id'         => $pledge->id,
            'donor_email'=> 'ryan@example.test',
            'donor_name' => 'Ryan Miller',
        ]);
    }

    public function test_complete_payment_updates_transaction_user_and_address_and_redirects_to_thank_you_page(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name'  => 'Name',
            'email'      => 'old@example.test',
        ]);

        $tx = Transaction::factory()->create([
            'user_id'           => $user->id,
            'amount_cents'      => 2500,
            'status'            => 'pending',
            'payment_intent_id' => null,
            'payment_method_id' => null,
            'charge_id'         => null,
            'receipt_url'       => null,
            'type'              => 'one_time',
        ]);

        $payload = [
            'mode'              => 'payment',
            'transaction_id'    => $tx->id,
            'payment_intent_id' => 'pi_123',
            'payment_method_id' => 'pm_123',
            'charge_id'         => 'ch_123',
            'receipt_url'       => 'https://example.test/stripe-receipt',
            'donor_first_name'  => 'Ryan',
            'donor_last_name'   => 'Miller',
            'donor_email'       => 'ryan@example.test',
            'donor_phone'       => '555-1234',
            'address_line1'     => '2429 S Halifax Way',
            'address_line2'     => '',
            'address_city'      => 'Aurora',
            'address_state'     => 'CO',
            'address_postal'    => '80013',
            'address_country'   => 'US',
        ];

        $response = $this
            ->actingAs($user)
            ->post(route('donations.complete'), $payload);

        $response->assertRedirect(route('donations.thankyou', $tx));

        // Transaction has all the Stripe IDs + receipt URL and payer info now
        $this->assertDatabaseHas('transactions', [
            'id'                => $tx->id,
            'payment_intent_id' => 'pi_123',
            'payment_method_id' => 'pm_123',
            'charge_id'         => 'ch_123',
            'receipt_url'       => 'https://example.test/stripe-receipt',
            'status'            => 'succeeded',
            'payer_email'       => 'ryan@example.test',
            'payer_name'        => 'Ryan Miller',
        ]);

        // Primary address upserted
        $this->assertDatabaseHas('addresses', [
            'user_id'     => $user->id,
            'line1'       => '2429 S Halifax Way',
            'city'        => 'Aurora',
            'state'       => 'CO',
            'postal_code' => '80013',
            'country'     => 'US',
            'is_primary'  => true,
        ]);

        // User basic info updated from donor fields
        $this->assertDatabaseHas('users', [
            'id'         => $user->id,
            'first_name' => 'Ryan',
            'last_name'  => 'Miller',
            'email'      => 'ryan@example.test',
        ]);
    }

    public function test_thank_you_page_shows_donation_summary_and_receipt_link(): void
    {
        $user = User::factory()->create();

        $tx = Transaction::factory()->create([
            'user_id'      => $user->id,
            'amount_cents' => 2500,
            'status'       => 'succeeded',
            'currency'     => 'usd',
            'type'         => 'one_time',
            'receipt_url'  => 'https://example.test/stripe-receipt',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('donations.thankyou', $tx));

        $response->assertOk();
        $response->assertSee('$25.00');
        $response->assertSee('Thank you for your gift');
        $response->assertSee($tx->receipt_url);
    }

    public function test_thank_you_subscription_page_shows_latest_receipt_link_when_transaction_exists(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::create([
            'user_id'      => $user->id,
            'amount_cents' => 777,
            'currency'     => 'usd',
            'interval'     => 'month',
            'status'       => 'active',
            'donor_email'  => 'ryan@example.test',
            'donor_name'   => 'Ryan Miller',
            'metadata'     => [],
        ]);

        // Older recurring transaction
        Transaction::factory()->create([
            'user_id'          => $user->id,
            'pledge_id'        => $pledge->id,
            'subscription_id'  => 'sub_123',
            'amount_cents'     => 777,
            'currency'         => 'usd',
            'type'             => 'subscription_recurring',
            'status'           => 'succeeded',
            'receipt_url'      => 'https://example.test/old-receipt',
            'paid_at'          => now()->subMonth(),
        ]);

        // Newest recurring transaction (the one we expect to see)
        $latest = Transaction::factory()->create([
            'user_id'          => $user->id,
            'pledge_id'        => $pledge->id,
            'subscription_id'  => 'sub_123',
            'amount_cents'     => 777,
            'currency'         => 'usd',
            'type'             => 'subscription_recurring',
            'status'           => 'succeeded',
            'receipt_url'      => 'https://example.test/latest-receipt',
            'paid_at'          => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('donations.thankyou-subscription', $pledge));

        $response->assertOk();
        $response->assertSee('Thank you for your monthly gift');
        $response->assertSee('View Stripe receipt');
        $response->assertSee($latest->receipt_url);
    }
}
