<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurgeUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_soft_deletes_unverified_users_older_than_the_cutoff_and_leaves_verified_users_alone(): void
    {
        Carbon::setTestNow('2026-01-25 12:00:00');

        $oldUnverified = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(4),
        ]);

        $newUnverified = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(1),
        ]);

        $oldVerified = User::factory()->create([
            'email_verified_at' => now()->subDays(3),
            'created_at' => now()->subDays(4),
        ]);

        $this->artisan('users:purge-unverified --days=3')
            ->assertExitCode(0);

        $this->assertSoftDeleted('users', ['id' => $oldUnverified->id]);
        $this->assertDatabaseHas('users', ['id' => $newUnverified->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $oldVerified->id, 'deleted_at' => null]);
    }
}
