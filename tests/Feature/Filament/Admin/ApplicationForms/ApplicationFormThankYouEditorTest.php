<?php

namespace Tests\Feature\Filament\Admin\ApplicationForms;

use App\Filament\Resources\ApplicationForms\Pages\CreateApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Models\ApplicationForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithFilamentAdmin;
use Tests\TestCase;

class ApplicationFormThankYouEditorTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFilamentAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        if (method_exists($this, 'bootFilamentPermissions')) {
            $this->bootFilamentPermissions();
        }

        $this->loginAsSuperAdmin();
    }

    #[Test]
    public function it_can_create_with_plain_text_thank_you(): void
    {
        Livewire::test(CreateApplicationForm::class)
            ->fillForm([
                'name' => 'Volunteer Form',
                'slug' => 'volunteer-form',
                'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
                'thank_you_text' => "Thanks!\nWe will reach out soon.",
                'is_active' => true,
                'use_availability' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('application_forms', [
            'slug' => 'volunteer-form',
            'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
            'thank_you_content' => "Thanks!\nWe will reach out soon.",
        ]);
    }

    #[Test]
    public function it_can_create_with_html_thank_you(): void
    {
        Livewire::test(CreateApplicationForm::class)
            ->fillForm([
                'name' => 'Volunteer Form HTML',
                'slug' => 'volunteer-form-html',
                'thank_you_format' => ApplicationForm::THANK_YOU_HTML,
                'thank_you_html' => '<div><h3>Thank you!</h3><p>We will be in touch.</p></div>',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $form = ApplicationForm::whereSlug('volunteer-form-html')->firstOrFail();

        $this->assertSame(ApplicationForm::THANK_YOU_HTML, $form->thank_you_format);
        $this->assertStringContainsString('Thank you!', (string) $form->thank_you_content);
        $this->assertStringContainsString('We will be in touch.', (string) $form->thank_you_content);
    }

    #[Test]
    public function it_can_edit_and_save_html_thank_you(): void
    {
        $form = ApplicationForm::factory()->create([
            'slug' => 'edit-html-form',
            'thank_you_format' => ApplicationForm::THANK_YOU_TEXT,
            'thank_you_content' => 'Old',
        ]);

        Livewire::test(EditApplicationForm::class, ['record' => $form->getKey()])
            ->fillForm([
                'thank_you_format' => ApplicationForm::THANK_YOU_HTML,
                'thank_you_html' => '<div><h3>Thank you!</h3><p>We will be in touch.</p></div>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $form->fresh();

        $this->assertSame(ApplicationForm::THANK_YOU_HTML, $fresh->thank_you_format);
        $this->assertStringContainsString('<h3>Thank you!</h3>', (string) $fresh->thank_you_content);
        $this->assertStringContainsString('We will be in touch.', (string) $fresh->thank_you_content);
    }

    #[Test]
    public function it_sanitizes_malicious_html_on_create(): void
    {
        Livewire::test(CreateApplicationForm::class)
            ->fillForm([
                'name' => 'Volunteer Form Malicious',
                'slug' => 'volunteer-form-malicious',
                'thank_you_format' => ApplicationForm::THANK_YOU_HTML,
                'thank_you_html' => '<p onclick="alert(1)">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">X</a>',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $form = ApplicationForm::whereSlug('volunteer-form-malicious')->firstOrFail();

        $html = (string) $form->thank_you_content;

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $this->assertStringContainsString('<p', $html);
        $this->assertStringContainsString('Hi', $html);
    }
}
