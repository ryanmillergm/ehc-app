<?php

namespace Tests\Feature\Email;

use App\Livewire\EmailSignupForm;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailSignupFormTest extends TestCase
{
    use RefreshDatabase;

    private function seedDefaultList(): EmailList
    {
        return EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'description' => 'Monthly updates and stories.',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);
    }

    #[Test]
    public function it_requires_an_email(): void
    {
        $this->seedDefaultList();

        Livewire::test(EmailSignupForm::class)
            ->set('email', '')
            ->call('submit')
            ->assertHasErrors(['email' => 'required']);
    }

    #[Test]
    public function it_requires_a_valid_email(): void
    {
        $this->seedDefaultList();

        Livewire::test(EmailSignupForm::class)
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['email' => 'email']);
    }

    #[Test]
    public function it_creates_a_subscriber_and_normalizes_email_and_subscribes_to_default_lists(): void
    {
        $list = $this->seedDefaultList();

        Livewire::test(EmailSignupForm::class, ['variant' => 'page'])
            ->set('first_name', 'Ryan')
            ->set('last_name', 'M')
            ->set('email', '  TEST@Example.COM ')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('email', '')
            ->assertSet('first_name', '')
            ->assertSet('last_name', '');

        $subscriber = EmailSubscriber::query()
            ->where('email', 'test@example.com')
            ->first();

        $this->assertNotNull($subscriber);
        $this->assertSame('Ryan', $subscriber->first_name);
        $this->assertSame('M', $subscriber->last_name);

        // computed accessor (not a DB column)
        $this->assertSame('Ryan M', $subscriber->name);

        $this->assertNotNull($subscriber->subscribed_at);
        $this->assertNull($subscriber->unsubscribed_at);
        $this->assertNotEmpty($subscriber->unsubscribe_token);

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $list->id,
        ]);

        // optional: ensure they are not unsubscribed on the pivot
        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $list->id,
            'unsubscribed_at' => null,
        ]);
    }

    #[Test]
    public function it_does_not_duplicate_an_existing_subscribed_email(): void
    {
        $this->seedDefaultList();

        EmailSubscriber::create([
            'email' => 'a@b.com',
            'first_name' => 'First',
            'last_name' => null,
            'unsubscribe_token' => 'token-1',
            'subscribed_at' => now(),
        ]);

        Livewire::test(EmailSignupForm::class, ['variant' => 'page'])
            ->set('first_name', 'Second')
            ->set('last_name', 'Person')
            ->set('email', 'A@B.COM')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, EmailSubscriber::query()->where('email', 'a@b.com')->count());
    }

    #[Test]
    public function it_resubscribes_if_previously_unsubscribed(): void
    {
        $this->seedDefaultList();

        $subscriber = EmailSubscriber::create([
            'email' => 'u@b.com',
            'first_name' => 'User',
            'last_name' => null,
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

    #[Test]
    public function it_treats_gmail_dot_and_plus_alias_as_the_same_subscriber(): void
    {
        $this->seedDefaultList();

        EmailSubscriber::create([
            'email' => 'ryan@gmail.com',
            'unsubscribe_token' => 'token-1',
            'subscribed_at' => now(),
        ]);

        Livewire::test(EmailSignupForm::class)
            ->set('email', 'r.y.a.n+tag@googlemail.com')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, EmailSubscriber::query()->where('email', 'ryan@gmail.com')->count());

        $this->assertDatabaseHas('email_subscribers', [
            'email' => 'ryan@gmail.com',
        ]);
    }

    #[Test]
    public function footer_variant_does_not_require_names(): void
    {
        $this->seedDefaultList();

        Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'test@example.com')
            ->call('submit')
            ->assertHasNoErrors();

        $subscriber = EmailSubscriber::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertNull($subscriber->first_name);
        $this->assertNull($subscriber->last_name);
    }

    #[Test]
    public function it_auto_subscribes_to_default_marketing_lists_on_signup(): void
    {
        EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        EmailList::create([
            'key' => 'events',
            'label' => 'Events',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'test@example.com')
            ->call('submit')
            ->assertHasNoErrors();

        $subscriber = EmailSubscriber::where('email', 'test@example.com')->firstOrFail();

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => EmailList::where('key', 'newsletter')->value('id'),
        ]);

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => EmailList::where('key', 'events')->value('id'),
        ]);
    }

    #[Test]
    public function page_variant_requires_first_and_last_name(): void
    {
        EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\EmailSignupForm::class, ['variant' => 'page'])
            ->set('email', 'test@example.com')
            ->set('first_name', '')
            ->set('last_name', '')
            ->call('submit')
            ->assertHasErrors(['first_name' => 'required', 'last_name' => 'required']);
    }

    #[Test]
    public function it_strips_plus_tags_for_non_gmail_too(): void
    {
        EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        \Livewire\Livewire::test(\App\Livewire\EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'me+tag@yahoo.com')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('email_subscribers', [
            'email' => 'me@yahoo.com',
        ]);
    }
}
