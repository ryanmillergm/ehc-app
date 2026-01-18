<?php

namespace Tests\Feature\Filament\Volunteers;

use App\Filament\Resources\VolunteerNeeds\Pages\CreateVolunteerNeed;
use App\Filament\Resources\VolunteerNeeds\Pages\EditVolunteerNeed;
use App\Filament\Resources\VolunteerNeeds\Pages\ListVolunteerNeeds;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithFilamentAdmin;
use Tests\TestCase;

class VolunteerNeedResourceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFilamentAdmin;

    #[Test]
    public function list_page_can_render_and_show_records(): void
    {
        $this->loginAsSuperAdmin();

        $needs = VolunteerNeed::factory()->count(3)->create();

        Livewire::test(ListVolunteerNeeds::class)
            ->assertOk()
            ->assertCanSeeTableRecords($needs);
    }

    #[Test]
    public function create_page_can_create_a_volunteer_need(): void
    {
        $this->loginAsSuperAdmin();

        Livewire::test(CreateVolunteerNeed::class)
            ->fillForm([
                'title' => 'Setup Crew',
                'slug' => 'setup-crew',
                'description' => 'Helps with setup and teardown.',
                'is_active' => true,
                'capacity' => 10,
                // include 'event_id' only if your form contains it
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('volunteer_needs', [
            'title' => 'Setup Crew',
            'slug' => 'setup-crew',
            'is_active' => 1,
        ]);
    }

    #[Test]
    public function create_page_requires_unique_slug(): void
    {
        $this->loginAsSuperAdmin();

        VolunteerNeed::factory()->create([
            'title' => 'Food Service',
            'slug'  => 'food-service',
        ]);

        Livewire::test(CreateVolunteerNeed::class)
            ->fillForm([
                'title' => 'Another Food Service',
                'slug' => 'food-service',
                'description' => 'Duplicate slug test.',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'slug' => 'unique',
            ]);
    }

    #[Test]
    public function edit_page_can_update_a_volunteer_need(): void
    {
        $this->loginAsSuperAdmin();

        $need = VolunteerNeed::factory()->create([
            'title' => 'Food Service',
            'slug' => 'food-service',
            'is_active' => true,
        ]);

        Livewire::test(EditVolunteerNeed::class, ['record' => $need->getRouteKey()])
            ->fillForm([
                'title' => 'Food Service Team',
                'slug' => 'food-service',
                'is_active' => false,
                'capacity' => 25,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('volunteer_needs', [
            'id' => $need->id,
            'title' => 'Food Service Team',
            'slug' => 'food-service',
            'is_active' => 0,
            'capacity' => 25,
        ]);
    }
}
