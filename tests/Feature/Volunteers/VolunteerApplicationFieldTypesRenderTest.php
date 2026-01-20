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

class VolunteerApplicationFieldTypesRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_all_supported_dynamic_field_types(): void
    {
        // Route is auth-protected.
        $this->actingAs(User::factory()->create());

        // Creating the form auto-creates + attaches the global "message" field via ApplicationForm::booted()
        $form = ApplicationForm::factory()->create([
            'is_active'        => true,
            'use_availability' => false,
        ]);

        $need = VolunteerNeed::factory()->create([
            'is_active'           => true,
            'application_form_id' => $form->id,
        ]);

        // TEXT
        $text = FormField::factory()->create([
            'key'   => 'full_name',
            'type'  => 'text',
            'label' => 'Full name',
            'config' => [
                'placeholder' => 'John Doe',
            ],
        ]);
        $this->place($form, $text, sort: 10, required: true);

        // MESSAGE (textarea) - already exists globally because booted() created it
        $message = FormField::query()->where('key', 'message')->firstOrFail();

        // Ensure it's placed and has some predictable config for assertions
        FormFieldPlacement::query()->updateOrCreate(
            [
                'fieldable_type' => ApplicationForm::class,
                'fieldable_id'   => $form->id,
                'form_field_id'  => $message->id,
            ],
            [
                'is_required'    => true,
                'is_active'      => true,
                'sort'           => 20,
                'config_override'=> [
                    'rows' => 5,
                    'placeholder' => 'Share a bit...',
                ],
            ],
        );

        // SELECT (flat config shape)
        $select = FormField::factory()->create([
            'key'   => 'tshirt_size',
            'type'  => 'select',
            'label' => 'T-shirt size',
            'config' => [
                'S' => 'Small',
                'M' => 'Medium',
            ],
        ]);
        $this->place($form, $select, sort: 30);

        // RADIO (flat config shape)
        $radio = FormField::factory()->create([
            'key'       => 'radio-key',
            'type'      => 'radio',
            'label'     => 'Label for Radio Test',
            'help_text' => 'This is the test the radio buttons',
            'config' => [
                'option1' => 'option1',
                'option2' => 'option2',
            ],
        ]);
        $this->place($form, $radio, sort: 40, required: true);

        // CHECKBOX GROUP (nested config shape)
        $checkboxGroup = FormField::factory()->create([
            'key'   => 'interests',
            'type'  => 'checkbox_group',
            'label' => 'Areas of interest',
            'config' => [
                'options' => [
                    'food'   => 'Food',
                    'prayer' => 'Prayer',
                ],
            ],
        ]);
        $this->place($form, $checkboxGroup, sort: 50);

        // TOGGLE
        $toggle = FormField::factory()->create([
            'key'   => 'background_check',
            'type'  => 'toggle',
            'label' => 'Background check OK?',
        ]);
        $this->place($form, $toggle, sort: 60);

        // Hit the page
        $res = $this->get(route('volunteer.apply', $need));
        $res->assertOk();

        // --- TEXT ---
        $res->assertSee('Full name', false);
        $res->assertSee('wire:model.defer="answers.full_name"', false);
        $res->assertSee('placeholder="John Doe"', false);

        // --- MESSAGE (textarea) ---
        // Label comes from your global default ("Why do you want to volunteer?")
        $res->assertSee('Why do you want to volunteer?', false);
        $res->assertSee('wire:model.defer="answers.message"', false);
        // rows/placeholder come from placement config override we set above
        $res->assertSee('rows="5"', false);
        $res->assertSee('placeholder="Share a bit..."', false);

        // --- SELECT ---
        $res->assertSee('T-shirt size', false);
        $res->assertSee('wire:model.defer="answers.tshirt_size"', false);
        $res->assertSee('<option value="S">Small</option>', false);
        $res->assertSee('<option value="M">Medium</option>', false);

        // --- RADIO ---
        $res->assertSee('Label for Radio Test', false);
        $res->assertSee('This is the test the radio buttons', false);
        $res->assertSee('type="radio"', false);
        $res->assertSee('wire:model.defer="answers.radio-key"', false);
        $res->assertSee('value="option1"', false);
        $res->assertSee('value="option2"', false);
        $res->assertSee('option1', false);
        $res->assertSee('option2', false);

        // --- CHECKBOX GROUP ---
        $res->assertSee('Areas of interest', false);
        $res->assertSee('wire:model.defer="answers.interests"', false);
        $res->assertSee('value="food"', false);
        $res->assertSee('value="prayer"', false);

        // --- TOGGLE ---
        $res->assertSee('Background check OK?', false);
        $res->assertSee('wire:model.defer="answers.background_check"', false);
    }

    private function place(ApplicationForm $form, FormField $field, int $sort = 100, bool $required = false): FormFieldPlacement
    {
        return FormFieldPlacement::factory()->create([
            'fieldable_type'  => ApplicationForm::class,
            'fieldable_id'    => $form->id,
            'form_field_id'   => $field->id,
            'is_required'     => $required,
            'is_active'       => true,
            'sort'            => $sort,
            'config_override' => [],
        ]);
    }
}
