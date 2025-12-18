<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fix "now" so YTD is deterministic
        Carbon::setTestNow(Carbon::parse('2025-06-15 12:00:00'));
    }

    public function test_dashboard_exposes_lifetime_ytd_totals_and_active_recurring_count(): void
    {
        $user = User::factory()->create();

        // Successful, paid transactions for this user
        $t1 = Transaction::factory()->create([
            'user_id'      => $user->id,
            'status'       => 'succeeded',
            'amount_cents' => 3000, // $30
            'paid_at'      => Carbon::parse('2025-01-10'),
        ]);

        $t2 = Transaction::factory()->create([
            'user_id'      => $user->id,
            'status'       => 'succeeded',
            'amount_cents' => 2000, // $20
            'paid_at'      => Carbon::parse('2025-03-20'),
        ]);

        // Prior year – should count in lifetime but NOT YTD
        $t3 = Transaction::factory()->create([
            'user_id'      => $user->id,
            'status'       => 'succeeded',
            'amount_cents' => 5000, // $50
            'paid_at'      => Carbon::parse('2024-12-31'),
        ]);

        // Failed / unpaid / other user (should be ignored)
        Transaction::factory()->create([
            'user_id'      => $user->id,
            'status'       => 'failed',
            'amount_cents' => 9999,
            'paid_at'      => Carbon::parse('2025-02-01'),
        ]);

        Transaction::factory()->create([
            'user_id'      => $user->id,
            'status'       => 'succeeded',
            'amount_cents' => 7777,
            'paid_at'      => null,
        ]);

        Transaction::factory()->create([
            'user_id'      => User::factory()->create()->id,
            'status'       => 'succeeded',
            'amount_cents' => 1234,
            'paid_at'      => Carbon::parse('2025-01-01'),
        ]);

        // Active + inactive recurring
        Pledge::factory()->create([
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        Pledge::factory()->create([
            'user_id' => $user->id,
            'status'  => 'active',
        ]);

        Pledge::factory()->create([
            'user_id' => $user->id,
            'status'  => 'cancelled',
        ]);

        // Hit the dashboard
        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');

        $expectedLifetime = (3000 + 2000 + 5000) / 100; // $100
        $expectedYtd      = (3000 + 2000) / 100;        // $50
        $expectedActive   = 2;

        $response->assertViewHas('lifetimeTotal', function ($value) use ($expectedLifetime) {
            $this->assertSame($expectedLifetime, $value);
            return true;
        });

        $response->assertViewHas('yearToDateTotal', function ($value) use ($expectedYtd) {
            $this->assertSame($expectedYtd, $value);
            return true;
        });

        $response->assertViewHas('activeRecurringCount', function ($value) use ($expectedActive) {
            $this->assertSame($expectedActive, $value);
            return true;
        });
    }

    public function test_recent_gifts_are_limited_to_five_and_sorted_newest_first(): void
    {
        $user = User::factory()->create();

        // Create 7 successful, paid transactions with different dates
        $transactions = collect();
        foreach (range(1, 7) as $i) {
            $transactions->push(
                Transaction::factory()->create([
                    'user_id'      => $user->id,
                    'status'       => 'succeeded',
                    'amount_cents' => 1000 * $i,
                    'paid_at'      => Carbon::parse("2025-01-" . str_pad($i, 2, '0', STR_PAD_LEFT)),
                ])
            );
        }

        // One extra from another user should never appear
        Transaction::factory()->create([
            'user_id'      => User::factory()->create()->id,
            'status'       => 'succeeded',
            'amount_cents' => 9999,
            'paid_at'      => Carbon::parse('2025-01-20'),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertViewHas('recentGifts', function ($collection) use ($transactions) {
            $this->assertCount(5, $collection);

            // Expect the 5 newest by paid_at desc => items 7,6,5,4,3
            $expectedIds = $transactions->sortByDesc('paid_at')->take(5)->pluck('id')->values()->all();
            $actualIds   = $collection->pluck('id')->values()->all();

            $this->assertSame($expectedIds, $actualIds);

            return true;
        });
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(); // Jetstream / auth middleware
    }
}
