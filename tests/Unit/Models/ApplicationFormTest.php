<?php

namespace Tests\Unit\Models;

use App\Models\ApplicationForm;
use App\Models\FormField;
use App\Models\FormFieldPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_auto_generates_slug_when_missing(): void
    {
        $form = ApplicationForm::create([
            'name' => 'Volunteer General Form',
            'slug' => null,
            'is_active' => true,
            'use_availability' => true,
        ]);

        $this->assertSame('volunteer-general-form', $form->fresh()->slug);
    }

    #[Test]
    public function it_creates_default_message_field_and_attaches_it_via_placement_on_create(): void
    {
        $form = ApplicationForm::create([
            'name' => 'Volunteer Form',
            'slug' => 'volunteer-form',
            'is_active' => true,
            'use_availability' => true,
        ]);

        // Global field exists
        $this->assertDatabaseHas('form_fields', [
            'key'  => 'message',
            'type' => 'textarea',
        ]);

        $messageField = FormField::query()->where('key', 'message')->firstOrFail();

        // Placement exists for this form + that field
        $this->assertDatabaseHas('form_field_placements', [
            'fieldable_type' => ApplicationForm::class,
            'fieldable_id'   => $form->id,
            'form_field_id'  => $messageField->id,
            'is_required'    => 1,
            'is_active'      => 1,
            'sort'           => 10,
        ]);
    }

    #[Test]
    public function it_does_not_duplicate_message_field_placement(): void
    {
        $form = ApplicationForm::create([
            'name' => 'Volunteer Form',
            'slug' => 'volunteer-form',
            'is_active' => true,
            'use_availability' => true,
        ]);

        $messageField = FormField::query()->where('key', 'message')->firstOrFail();

        $this->assertSame(
            1,
            FormFieldPlacement::query()
                ->where('fieldable_type', ApplicationForm::class)
                ->where('fieldable_id', $form->id)
                ->where('form_field_id', $messageField->id)
                ->count()
        );

        // Updating the form should NOT create a second placement
        $form->update(['description' => 'updated']);

        $this->assertSame(
            1,
            FormFieldPlacement::query()
                ->where('fieldable_type', ApplicationForm::class)
                ->where('fieldable_id', $form->id)
                ->where('form_field_id', $messageField->id)
                ->count()
        );
    }
}
