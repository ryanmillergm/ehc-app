<?php

namespace Tests\Feature\Donations;

use App\Http\Controllers\Donations\DonationsController;
use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Charge;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Tests\TestCase;

class DonationsControllerTest extends TestCase
{
    use RefreshDatabase;

    private const START_URI    = '/__test/donations/start';
    private const COMPLETE_URI = '/__test/donations/complete';

    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];

        // Test-only endpoints hitting controller
        $router->middleware('web')->post(self::START_URI, [DonationsController::class, 'start']);
        $router->middleware('web')->post(self::COMPLETE_URI, [DonationsController::class, 'complete']);

        // The controller returns JSON like: ['redirect' => route('donations.thankyou')]
        if (! $router->has('donations.show')) {
            $router->middleware('web')->get('/donations', fn () => 'ok')->name('donations.show');
        }
        if (! $router->has('donations.thankyou')) {
            $router->middleware('web')->get('/donations/thankyou', fn () => 'ok')->name('donations.thankyou');
        }
        if (! $router->has('donations.thankyou-subscription')) {
            $router->middleware('web')->get('/donations/thankyou-subscription', fn () => 'ok')->name('donations.thankyou-subscription');
        }
    }

    #[Test]
    public function one_time_start_creates_transaction_and_returns_client_secret(): void
    {
        $user = User::factory()->create();

        $stripe = $this->createMock(StripeService::class);

        // Mimic StripeService side-effects: set PI id + status on the tx
        $stripe->method('createOneTimePaymentIntent')
            ->willReturnCallback(function (Transaction $tx, array $donor) {
                $pi = $this->pi([
                    'id'            => 'pi_test_123',
                    'status'        => 'requires_payment_method',
                    'client_secret' => 'cs_test_123',
                ]);

                $tx->payment_intent_id = $pi->id;
                $tx->status            = $pi->status;
                $tx->save();

                return $pi;
            });

        $this->app->instance(StripeService::class, $stripe);

        $res = $this->actingAs($user)->postJson(self::START_URI, [
            'amount'    => 25,
            'frequency' => 'one_time',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['mode', 'attemptId', 'transactionId', 'clientSecret']);

        $this->assertSame('payment', $res->json('mode'));
        $this->assertSame('cs_test_123', $res->json('clientSecret'));

        $tx = Transaction::findOrFail($res->json('transactionId'));

        $this->assertSame('one_time', $tx->type);
        $this->assertSame('donation_widget', $tx->source);
        $this->assertSame(2500, (int) $tx->amount_cents);

        // After StripeService call, status matches PI status
        $this->assertSame('requires_payment_method', $tx->status);
        $this->assertSame('pi_test_123', $tx->payment_intent_id);

        $meta = $this->meta($tx->metadata);
        $this->assertSame('one_time', $meta['frequency'] ?? null);
    }

    #[Test]
    public function one_time_complete_enriches_metadata_including_card_exp_month_and_year(): void
    {
        $user = User::factory()->create();

        $tx = Transaction::create([
            'attempt_id'   => 'attempt_abc',
            'user_id'      => $user->id,
            'amount_cents' => 60000,
            'currency'     => 'usd',
            'type'         => 'one_time',
            'status'       => 'pending',
            'source'       => 'donation_widget',
            'metadata'     => ['frequency' => 'one_time'],
        ]);

        $stripe = $this->createMock(StripeService::class);

        $stripe->method('retrievePaymentIntent')->willReturn(
            $this->pi([
                'id'            => 'pi_test_999',
                'status'        => 'succeeded',
                'customer'      => 'cus_test_1',
                'payment_method'=> 'pm_test_1',
                'latest_charge' => 'ch_test_1',
            ])
        );

        $stripe->method('retrieveCharge')->willReturn(
            $this->charge([
                'id'              => 'ch_test_1',
                'payment_intent'  => 'pi_test_999',
                'customer'        => 'cus_test_1',
                'payment_method'  => 'pm_test_1',
                'amount'          => 60000,
                'currency'        => 'usd',
                'receipt_url'     => 'https://example.test/receipt',
                'billing_details' => [
                    'email' => 'ryan@example.test',
                    'name'  => 'Ryan Miller',
                ],
                'payment_method_details' => [
                    'card' => [
                        'brand'     => 'visa',
                        'last4'     => '4242',
                        'country'   => 'US',
                        'funding'   => 'credit',
                        'exp_month' => 12,
                        'exp_year'  => 2026,
                    ],
                ],
            ])
        );

        $this->app->instance(StripeService::class, $stripe);

        $res = $this->actingAs($user)->postJson(self::COMPLETE_URI, [
            'mode'              => 'payment',
            'transaction_id'    => $tx->id,
            'payment_intent_id' => 'pi_test_999',
            'donor_first_name'  => 'Ryan',
            'donor_last_name'   => 'Miller',
            'donor_email'       => 'ryan@example.test',
        ]);

        $res->assertOk()->assertJsonStructure(['redirect']);

        $tx->refresh();

        $this->assertSame('succeeded', $tx->status);
        $this->assertNotNull($tx->paid_at);

        $meta = $this->meta($tx->metadata);

        $this->assertSame('one_time', $meta['frequency'] ?? null);
        $this->assertSame('visa', $meta['card_brand'] ?? null);
        $this->assertSame('4242', $meta['card_last4'] ?? null);
        $this->assertSame('US', $meta['card_country'] ?? null);
        $this->assertSame('credit', $meta['card_funding'] ?? null);

        // The “missing fields” we want to lock in
        $this->assertSame(12, $meta['card_exp_month'] ?? null);
        $this->assertSame(2026, $meta['card_exp_year'] ?? null);
    }

    #[Test]
    public function monthly_start_creates_pledge_and_returns_setup_intent_client_secret(): void
    {
        $user = User::factory()->create();

        $stripe = $this->createMock(StripeService::class);

        // Mimic StripeService side-effects: set SI id on pledge
        $stripe->method('createSetupIntentForPledge')
            ->willReturnCallback(function (Pledge $pledge, array $donor) {
                $si = $this->si([
                    'id'            => 'seti_test_1',
                    'status'        => 'requires_payment_method',
                    'customer'      => 'cus_test_1',
                    'client_secret' => 'cs_seti_test_1',
                ]);

                $pledge->setup_intent_id = $si->id;
                $pledge->save();

                return $si;
            });

        $this->app->instance(StripeService::class, $stripe);

        $res = $this->actingAs($user)->postJson(self::START_URI, [
            'amount'    => 12,
            'frequency' => 'monthly',
        ]);

        $res->assertOk()
            ->assertJsonStructure(['mode', 'attemptId', 'pledgeId', 'clientSecret']);

        $this->assertSame('subscription', $res->json('mode'));
        $this->assertSame('cs_seti_test_1', $res->json('clientSecret'));

        $pledge = Pledge::findOrFail($res->json('pledgeId'));

        $this->assertSame('month', $pledge->interval);
        $this->assertSame(1200, (int) $pledge->amount_cents);
        $this->assertSame('incomplete', $pledge->status);
        $this->assertSame('seti_test_1', $pledge->setup_intent_id);

        $meta = $this->meta($pledge->metadata);
        $this->assertSame('monthly', $meta['frequency'] ?? null);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function pi(array $data): PaymentIntent
    {
        return PaymentIntent::constructFrom($data, null);
    }

    private function si(array $data): SetupIntent
    {
        return SetupIntent::constructFrom($data, null);
    }

    private function charge(array $data): Charge
    {
        return Charge::constructFrom($data, null);
    }

    private function meta($value): array
    {
        if (is_array($value)) return $value;

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return $decoded;
        }

        return [];
    }
}
