<?php

namespace Tests\Feature\Volunteers;

use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use App\Models\User;
use App\Models\VolunteerNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VolunteerApplicationRadioOptionsRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function radio_field_renders_options_when_config_is_flat_key_value_pairs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Given a need + active form
        $form = ApplicationForm::factory()->create([
            'is_active'         => true,
            'use_availability'  => false,
        ]);

        $need = VolunteerNeed::factory()->create([
            'is_active'            => true,
            'application_form_id'  => $form->id,
        ]);

        $field = FormField::factory()->create([
            'key'       => 'radio-key',
            'type'      => 'radio',
            'label'     => 'Label for Radio Test',
            'help_text' => 'This is the test the radio buttons',
            'config'    => [
                'option1' => 'option1',
                'option2' => 'option2',
            ],
        ]);

        // And it is placed on the form
        FormFieldPlacement::factory()->create([
            'fieldable_type'   => ApplicationForm::class,
            'fieldable_id'     => $form->id,
            'form_field_id'    => $field->id,
            'is_required'      => true,
            'is_active'        => true,
            'sort'             => 100,
            'config_override'  => [],
        ]);

        // When I view the volunteer application page
        $res = $this->get(route('volunteer.apply', $need));

        // Then the radio group and options render
        $res->assertOk();

        $res->assertSee('Label for Radio Test', false);
        $res->assertSee('This is the test the radio buttons', false);

        // Options should exist as actual radio inputs
        $res->assertSee('type="radio"', false);
        $res->assertSee('value="option1"', false);
        $res->assertSee('value="option2"', false);

        // And their labels should appear
        $res->assertSee('option1', false);
        $res->assertSee('option2', false);
    }
}
