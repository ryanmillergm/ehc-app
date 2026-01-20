<?php

namespace Tests\Unit\Models;

use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VolunteerNeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_many_applications(): void
    {
        $need = VolunteerNeed::factory()->create();

        $apps = VolunteerApplication::factory()
            ->count(2)
            ->create(['volunteer_need_id' => $need->id]);

        $this->assertCount(2, $need->applications);
        $this->assertTrue($need->applications->contains($apps->first()));
    }

    #[Test]
    public function factory_general_state_sets_expected_fields(): void
    {
        $need = VolunteerNeed::factory()->general()->create();

        $this->assertSame('General Volunteer', $need->title);
        $this->assertSame('general', $need->slug);
        $this->assertTrue($need->is_active);
        $this->assertNotEmpty($need->description);
    }
}
