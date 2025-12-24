<?php

namespace Tests\Feature\Email;

use App\Livewire\EmailSignupForm;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailSignupFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_requires_an_email(): void
    {
        Livewire::test(EmailSignupForm::class)
            ->set('email', '')
            ->call('submit')
            ->assertHasErrors(['email' => 'required']);
    }

    #[Test]
    public function it_requires_a_valid_email(): void
    {
        Livewire::test(EmailSignupForm::class)
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email' => 'email']);
    }

    #[Test]
    public function it_creates_a_subscriber_and_normalizes_email(): void
    {
        Livewire::test(EmailSignupForm::class)
            ->set('name', 'Ryan')
            ->set('email', '  TEST@Example.COM ')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('email', '')
            ->assertSet('name', '');

        $subscriber = EmailSubscriber::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($subscriber);
        $this->assertSame('Ryan', $subscriber->name);
        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNotEmpty($subscriber->unsubscribe_token);
    }

    #[Test]
    public function it_does_not_duplicate_an_existing_subscribed_email(): void
    {
        EmailSubscriber::create([
            'email' => 'a@b.com',
            'name' => 'First',
            'unsubscribe_token' => 'token-1',
            'subscribed_at' => now(),
        ]);

        Livewire::test(EmailSignupForm::class)
            ->set('name', 'Second')
            ->set('email', 'A@B.COM')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, EmailSubscriber::query()->where('email', 'a@b.com')->count());
    }

    #[Test]
    public function it_resubscribes_if_previously_unsubscribed(): void
    {
        $subscriber = EmailSubscriber::create([
            'email' => 'u@b.com',
            'name' => 'User',
            'unsubscribe_token' => 'token-2',
            'subscribed_at' => now()->subDays(10),
            'unsubscribed_at' => now()->subDay(),
        ]);

        Livewire::test(EmailSignupForm::class)
            ->set('email', 'u@b.com')
            ->call('submit')
            ->assertHasNoErrors();

        $subscriber->refresh();

        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNotNull($subscriber->subscribed_at);
    }
}
