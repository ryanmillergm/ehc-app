<?php

namespace Tests\Feature\Email;

use App\Livewire\EmailSignupForm;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailSignupFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.secret' => 'test-secret',
            'services.turnstile.key' => 'test-site-key',
        ]);

        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify*' => Http::response(['success' => true], 200),
        ]);
    }

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
            ->set('turnstileToken', 'token-1')
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
            ->set('turnstileToken', 'token-1')
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
            ->set('turnstileToken', 'token-1')
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
            ->set('turnstileToken', 'token-1')
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
            ->set('turnstileToken', 'token-1')
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
            ->set('turnstileToken', 'token-1')
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
            ->set('turnstileToken', 'token-1')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('email_subscribers', [
            'email' => 'me+tag@yahoo.com',
            'email_canonical' => 'me@yahoo.com',
        ]);
    }

    #[Test]
    public function it_requires_turnstile_token_for_public_variants(): void
    {
        $this->seedDefaultList();

        Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'token-required-footer@example.com')
            ->call('submit')
            ->assertHasErrors(['turnstileToken' => 'required']);

        Livewire::test(EmailSignupForm::class, ['variant' => 'page'])
            ->set('email', 'token-required-page@example.com')
            ->set('first_name', 'Token')
            ->set('last_name', 'Required')
            ->call('submit')
            ->assertHasErrors(['turnstileToken' => 'required']);
    }

    #[Test]
    public function it_requires_a_new_turnstile_token_for_a_second_submission_attempt(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'repeat@example.com')
            ->set('turnstileToken', 'token-1')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('turnstileToken', null);

        $component
            ->set('email', 'repeat@example.com')
            ->call('submit')
            ->assertHasErrors(['turnstileToken' => 'required']);

        $component
            ->set('turnstileToken', 'token-2')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, EmailSubscriber::query()->where('email', 'repeat@example.com')->count());
    }

    #[Test]
    public function it_resets_turnstile_token_after_submit_and_allows_retry_with_new_token(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'turnstile-retry@example.com')
            ->set('turnstileToken', 'token-1')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('turnstileToken', null);

        $this->assertContains($component->get('bannerType'), ['success', 'info']);

        $component
            ->set('email', 'turnstile-retry@example.com')
            ->set('turnstileToken', 'token-2')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('turnstileToken', null);

        $this->assertDatabaseHas('email_subscribers', [
            'email' => 'turnstile-retry@example.com',
        ]);
    }

    #[Test]
    public function it_clears_previous_validation_and_banner_state_between_attempts(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', '')
            ->call('submit')
            ->assertHasErrors(['email' => 'required']);

        $component
            ->set('email', 'state-reset@example.com')
            ->set('turnstileToken', 'token-1')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bannerType', 'success');

        $component
            ->set('email', 'new-address@example.com')
            ->assertSet('bannerType', null)
            ->assertSet('bannerMessage', null);
    }

    #[Test]
    public function submit_shows_info_banner_and_creates_no_subscriber_when_turnstile_secret_is_missing(): void
    {
        $this->seedDefaultList();
        config(['services.turnstile.secret' => '']);

        Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'missing-secret@example.com')
            ->set('turnstileToken', 'token-any')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bannerType', 'info')
            ->assertSet('turnstileToken', null)
            ->assertSet('turnstileReady', false);

        $this->assertDatabaseMissing('email_subscribers', [
            'email' => 'missing-secret@example.com',
        ]);
    }

    #[Test]
    public function honeypot_submission_returns_success_banner_without_creating_a_subscriber(): void
    {
        $this->seedDefaultList();

        Livewire::test(EmailSignupForm::class, ['variant' => 'footer'])
            ->set('email', 'bot@example.com')
            ->set('company', 'Evil Bot Inc')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bannerType', 'success')
            ->assertSet('turnstileToken', null)
            ->assertSet('turnstileReady', false);

        $this->assertDatabaseMissing('email_subscribers', [
            'email' => 'bot@example.com',
        ]);
    }

    #[Test]
    public function page_variant_requires_a_new_turnstile_token_for_second_submission_attempt(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'page'])
            ->set('first_name', 'Page')
            ->set('last_name', 'User')
            ->set('email', 'page-repeat@example.com')
            ->set('turnstileToken', 'token-page-1')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('turnstileToken', null);

        $component
            ->set('first_name', 'Page')
            ->set('last_name', 'User')
            ->set('email', 'page-repeat@example.com')
            ->call('submit')
            ->assertHasErrors(['turnstileToken' => 'required']);
    }

    #[Test]
    public function repeated_submissions_do_not_create_duplicate_subscribers_for_the_same_canonical_email(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'footer']);

        foreach ([1, 2, 3] as $attempt) {
            $component
                ->set('email', 'RaCe.Test+tag@GMAIL.com')
                ->set('turnstileToken', 'token-race-' . $attempt)
                ->call('submit')
                ->assertHasNoErrors();
        }

        $this->assertSame(
            1,
            EmailSubscriber::query()->where('email_canonical', 'racetest@gmail.com')->count()
        );
    }

    #[Test]
    public function rate_limit_blocks_the_sixth_valid_attempt_and_prevents_new_rows(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'footer']);

        for ($i = 1; $i <= 5; $i++) {
            $component
                ->set('email', 'rate-limit@example.com')
                ->set('turnstileToken', 'token-rate-' . $i)
                ->call('submit')
                ->assertHasNoErrors();
        }

        $component
            ->set('email', 'rate-limit@example.com')
            ->set('turnstileToken', 'token-rate-6')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bannerType', 'info');

        $this->assertStringContainsString('Slow down, human.', (string) $component->get('bannerMessage'));
        $this->assertSame(1, EmailSubscriber::query()->where('email', 'rate-limit@example.com')->count());
    }

    #[Test]
    public function invalid_submissions_do_not_consume_rate_limit_budget(): void
    {
        $this->seedDefaultList();

        $component = Livewire::test(EmailSignupForm::class, ['variant' => 'footer']);

        for ($i = 1; $i <= 6; $i++) {
            $component
                ->set('email', 'not-an-email')
                ->set('turnstileToken', 'token-invalid-' . $i)
                ->call('submit')
                ->assertHasErrors(['email' => 'email']);
        }

        $component
            ->set('email', 'not-rate-limited@example.com')
            ->set('turnstileToken', 'token-valid')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bannerType', 'success');
    }
}
