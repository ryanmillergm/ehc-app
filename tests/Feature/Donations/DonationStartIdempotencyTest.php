<?php

namespace Tests\Feature\Donations;

use App\Models\Transaction;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Exception\IdempotencyException;
use Tests\TestCase;

class DonationStartIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function fakePi(string $id = 'pi_test', string $secret = 'cs_test'): \Stripe\PaymentIntent
    {
        return \Stripe\PaymentIntent::constructFrom([
            'id' => $id,
            'status' => 'requires_payment_method',
            'client_secret' => $secret,
        ], null);
    }

    #[Test]
    public function start_one_time_rotates_stale_client_attempt_id_and_does_not_500(): void
    {
        $staleAttempt = (string) Str::uuid();
        $pi = $this->fakePi('pi_123', 'cs_123');

        $this->mock(StripeService::class, function ($mock) use ($pi) {
            $mock->shouldReceive('createOneTimePaymentIntent')
                ->once()
                ->andReturn($pi);
        });

        $res = $this->postJson('/donations/start', [
            'attempt_id' => $staleAttempt,
            'amount'     => 10,
            'frequency'  => 'one_time',
        ]);

        $res->assertOk();

        $attemptId = $res->json('attemptId');
        $this->assertNotSame($staleAttempt, $attemptId);

        $this->assertDatabaseHas('transactions', [
            'attempt_id' => $attemptId,
            'type'       => 'one_time',
            'status'     => 'pending', // Stripe status set later by webhook/complete
            'source'     => 'donation_widget',
        ]);
    }

    #[Test]
    public function start_one_time_retries_on_stripe_idempotency_exception_and_rotates_attempt_id(): void
    {
        $initialAttempt = (string) Str::uuid();
        $pi = $this->fakePi('pi_456', 'cs_456');

        $calls = 0;

        $this->mock(StripeService::class, function ($mock) use (&$calls, $pi) {
            $mock->shouldReceive('createOneTimePaymentIntent')
                ->twice()
                ->andReturnUsing(function () use (&$calls, $pi) {
                    $calls++;

                    if ($calls === 1) {
                        throw new IdempotencyException('Keys for idempotent requests can only be used with the same parameters they were first used with.');
                    }

                    return $pi;
                });
        });

        $res = $this->postJson('/donations/start', [
            'attempt_id' => $initialAttempt,
            'amount'     => 10,
            'frequency'  => 'one_time',
        ]);

        $res->assertOk();

        $attemptId = $res->json('attemptId');
        $this->assertNotSame($initialAttempt, $attemptId);

        $this->assertDatabaseCount('transactions', 1);

        $tx = Transaction::firstOrFail();
        $this->assertSame($attemptId, $tx->attempt_id);
    }
}
