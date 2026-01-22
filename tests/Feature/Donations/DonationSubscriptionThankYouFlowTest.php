<?php

namespace Tests\Feature\Donations;

use App\Models\Pledge;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DonationSubscriptionThankYouFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Same reason as the one-time test: avoid config-related container explosions.
        config()->set('services.stripe.secret', 'sk_test_dummy');
        config()->set('services.stripe.debug_state', false);
    }

    #[Test]
    public function subscription_thankyou_requires_session(): void
    {
        $this->get(route('donations.thankyou-subscription'))
            ->assertNotFound();
    }

    #[Test]
    public function subscription_thankyou_renders_once_and_consumes_session(): void
    {
        $pledge = Pledge::factory()->create();

        // Optional, but makes the view less fragile if it expects a related transaction:
        Transaction::factory()->create([
            'pledge_id'   => $pledge->id,
            'type'        => 'subscription_initial',
            'status'      => 'succeeded',
            'amount_cents'=> 2500,
            'currency'    => 'usd',
            'paid_at'     => now(),
        ]);

        // Put the session key the controller expects:
        $this->withSession(['pledge_thankyou_id' => $pledge->id]);

        $page = $this->get(route('donations.thankyou-subscription'));
        $page->assertOk();

        // Second request should 404 because thankYouSubscription() uses session()->pull()
        $page2 = $this->get(route('donations.thankyou-subscription'));
        $page2->assertNotFound();
    }
}
