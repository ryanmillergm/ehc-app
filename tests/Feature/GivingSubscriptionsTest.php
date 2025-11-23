<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GivingSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_can_cancel_subscription_and_service_is_called(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::factory()->create([
            'user_id' => $user->id,
            'status'  => 'active',
            'cancel_at_period_end' => false,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('cancelSubscriptionAtPeriodEnd')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->is($pledge)));

        $this->app->instance(StripeService::class, $stripe);

        $this->actingAs($user)
            ->post(route('giving.subscriptions.cancel', $pledge))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_user_can_resume_subscription_and_service_is_called(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::factory()->create([
            'user_id' => $user->id,
            'status'  => 'active',
            'cancel_at_period_end' => true,
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('resumeSubscription')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->is($pledge)));

        $this->app->instance(StripeService::class, $stripe);

        $this
            ->actingAs($user)
            ->post(route('giving.subscriptions.resume', $pledge))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_user_can_update_subscription_amount_and_service_gets_cents(): void
    {
        $user = User::factory()->create();

        $pledge = Pledge::factory()->create([
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldReceive('updateSubscriptionAmount')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->is($pledge)),
                3700 // $37.00 -> cents
            );

        $this->app->instance(StripeService::class, $stripe);

        $this
            ->actingAs($user)
            ->post(route('giving.subscriptions.amount', $pledge), [
                'amount_dollars' => 37,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_user_cannot_manage_someone_elses_pledge(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $pledge = Pledge::factory()->create([
            'user_id' => $owner->id,
            'status'  => 'active',
        ]);

        $stripe = Mockery::mock(StripeService::class);
        $stripe->shouldNotReceive('cancelSubscriptionAtPeriodEnd');

        $this->app->instance(StripeService::class, $stripe);

        $this
            ->actingAs($other)
            ->post(route('giving.subscriptions.cancel', $pledge))
            ->assertStatus(403);
    }
}
