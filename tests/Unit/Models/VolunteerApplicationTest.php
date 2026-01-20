<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VolunteerApplicationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $app = VolunteerApplication::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($app->user->is($user));
    }

    #[Test]
    public function it_belongs_to_a_need(): void
    {
        $need = VolunteerNeed::factory()->create();
        $app = VolunteerApplication::factory()->create(['volunteer_need_id' => $need->id]);

        $this->assertTrue($app->need->is($need));
    }

    #[Test]
    public function it_can_have_a_reviewer(): void
    {
        $reviewer = User::factory()->create();

        $app = VolunteerApplication::factory()->create([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->assertTrue($app->reviewer->is($reviewer));
        $this->assertNotNull($app->reviewed_at);
    }

    #[Test]
    public function it_casts_interests_and_availability_to_arrays(): void
    {
        $app = VolunteerApplication::factory()->create([
            'interests' => ['food', 'prayer'],
            'availability' => ['thursday', 'sunday'],
        ]);

        $this->assertIsArray($app->interests);
        $this->assertIsArray($app->availability);
        $this->assertSame(['food', 'prayer'], $app->interests);
        $this->assertSame(['thursday', 'sunday'], $app->availability);
    }
}
