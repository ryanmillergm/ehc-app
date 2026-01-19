<?php

namespace Tests\Unit\Models;

use App\Models\ApplicationForm;
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
    public function it_creates_default_message_field_on_create(): void
    {
        $form = ApplicationForm::create([
            'name' => 'Volunteer Form',
            'slug' => 'volunteer-form',
            'is_active' => true,
            'use_availability' => true,
        ]);

        $this->assertDatabaseHas('application_form_fields', [
            'application_form_id' => $form->id,
            'key' => 'message',
            'type' => 'textarea',
            'is_required' => 1,
            'is_active' => 1,
        ]);
    }

    #[Test]
    public function it_does_not_duplicate_message_field(): void
    {
        $form = ApplicationForm::create([
            'name' => 'Volunteer Form',
            'slug' => 'volunteer-form',
            'is_active' => true,
            'use_availability' => true,
        ]);

        $form->update(['description' => 'updated']);

        $this->assertSame(
            1,
            $form->fields()->where('key', 'message')->count(),
        );
    }
}
