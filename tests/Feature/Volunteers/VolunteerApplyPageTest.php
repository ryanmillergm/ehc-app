<?php

namespace Tests\Feature\Volunteers;

use App\Livewire\Volunteers\Apply;
use App\Models\ApplicationForm;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VolunteerApplyPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_slug_page_renders_for_an_active_need_with_an_active_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $form = ApplicationForm::factory()->create([
            'is_active' => true,
        ]);

        $need = VolunteerNeed::factory()->create([
            'slug' => 'general',
            'is_active' => true,
            'application_form_id' => $form->id,
        ]);

        $res = $this->get(route('volunteer.apply', $need));

        $res->assertOk();
        $res->assertSee('Volunteer Application', false);
        $res->assertSee($need->title, false);
    }

    #[Test]
    public function it_validates_required_fields_and_saves_answers_json_on_submit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Form includes default "message" textarea from ApplicationForm::booted()
        $form = ApplicationForm::factory()->create([
            'is_active' => true,
            'use_availability' => true,
        ]);

        // Add an extra required field to prove dynamic validation works
        $form->fields()->create([
            'type' => 'text',
            'key' => 'city',
            'label' => 'City',
            'help_text' => null,
            'is_required' => true,
            'is_active' => true,
            'sort' => 20,
            'config' => [
                'min' => 2,
                'max' => 50,
            ],
        ]);

        $need = VolunteerNeed::factory()->create([
            'slug' => 'general',
            'is_active' => true,
            'application_form_id' => $form->id,
        ]);

        // Custom thank-you (text mode)
        $form->update([
            'thank_you_format' => 'text',
            'thank_you_content' => "Thanks Ryan!\nWe will contact you soon.",
        ]);

        // --- 1) Invalid submit -> should NOT create a record ---
        Livewire::test(Apply::class, ['need' => $need])
            ->set('answers.message', '')
            ->set('answers.city', '')
            ->call('submit')
            ->assertHasErrors([
                'answers.message' => ['required'],
                'answers.city' => ['required'],
            ]);

        $this->assertDatabaseMissing('volunteer_applications', [
            'user_id' => $user->id,
            'volunteer_need_id' => $need->id,
        ]);

        // --- 2) Valid submit -> should create + persist answers JSON + show thank-you ---
        $message = 'I would love to serve wherever needed.';
        $city = 'Denver';

        Livewire::test(Apply::class, ['need' => $need])
            ->set('answers.message', $message)
            ->set('answers.city', $city)
            ->set('availability.mon.am', true)
            ->set('availability.mon.pm', false)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSee('Thanks Ryan!')
            ->assertSee('We will contact you soon.');

        $this->assertDatabaseHas('volunteer_applications', [
            'user_id' => $user->id,
            'volunteer_need_id' => $need->id,
            'status' => VolunteerApplication::STATUS_SUBMITTED,
        ]);

        /** @var VolunteerApplication $app */
        $app = VolunteerApplication::query()
            ->where('user_id', $user->id)
            ->where('volunteer_need_id', $need->id)
            ->firstOrFail();

        $this->assertSame($message, data_get($app->answers, 'message'));
        $this->assertSame($city, data_get($app->answers, 'city'));

        // availability is injected into answers when toggle is on
        $this->assertTrue((bool) data_get($app->answers, 'availability.mon.am'));
        $this->assertFalse((bool) data_get($app->answers, 'availability.mon.pm'));
    }

    #[Test]
    public function it_returns_404_for_inactive_need_or_inactive_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $inactiveForm = ApplicationForm::factory()->create([
            'is_active' => false,
        ]);

        $needWithInactiveForm = VolunteerNeed::factory()->create([
            'slug' => 'general',
            'is_active' => true,
            'application_form_id' => $inactiveForm->id,
        ]);

        $this->get(route('volunteer.apply', $needWithInactiveForm))
            ->assertNotFound();

        $activeForm = ApplicationForm::factory()->create([
            'is_active' => true,
        ]);

        $inactiveNeed = VolunteerNeed::factory()->create([
            'slug' => 'inactive-need',
            'is_active' => false,
            'application_form_id' => $activeForm->id,
        ]);

        $this->get(route('volunteer.apply', $inactiveNeed))
            ->assertNotFound();
    }
}
