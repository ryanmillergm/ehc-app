<?php

namespace Tests\Feature\Email;

use App\Livewire\Profile\EmailPreferencesForm;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailPreferencesFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_subscriber_for_the_logged_in_user_and_attaches_default_marketing_lists(): void
    {
        $now = Carbon::parse('2026-01-04 08:00:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email' => 'test@example.com']);

        $defaultMarketing = EmailList::create([
            'key' => 'updates',
            'label' => 'Updates',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        $nonDefaultMarketing = EmailList::create([
            'key' => 'events',
            'label' => 'Events',
            'purpose' => 'marketing',
            'is_default' => false,
            'is_opt_outable' => true,
        ]);

        $transactional = EmailList::create([
            'key' => 'receipt',
            'label' => 'Receipts',
            'purpose' => 'transactional',
            'is_default' => false,
            'is_opt_outable' => false,
        ]);

        Livewire::actingAs($user)
            ->test(EmailPreferencesForm::class)
            ->assertOk();

        $this->assertDatabaseHas('email_subscribers', [
            'email' => 'test@example.com',
            'user_id' => $user->id,
        ]);

        $subscriber = EmailSubscriber::where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $defaultMarketing->id,
            'unsubscribed_at' => null,
        ]);

        $this->assertDatabaseMissing('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $nonDefaultMarketing->id,
        ]);

        $this->assertDatabaseMissing('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $transactional->id,
        ]);
    }

    #[Test]
    public function it_saves_marketing_list_opt_out_by_setting_pivot_unsubscribed_at(): void
    {
        $now = Carbon::parse('2026-01-05 09:15:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email' => 'test@example.com']);

        $list = EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'user_id' => $user->id,
            'unsubscribe_token' => str_repeat('x', 64),
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        $subscriber->lists()->attach($list->id, [
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(EmailPreferencesForm::class)
            ->set('subscriptions.'.$list->id, false)
            ->call('save')
            ->assertDispatched('saved');

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $list->id,
            'unsubscribed_at' => $now->toDateTimeString(),
        ]);
    }

    #[Test]
    public function it_does_not_allow_transactional_lists_to_be_unsubscribed_even_if_posted_false(): void
    {
        $now = Carbon::parse('2026-01-06 07:00:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email' => 'test@example.com']);

        $transactional = EmailList::create([
            'key' => 'receipt',
            'label' => 'Receipts',
            'purpose' => 'transactional',
            'is_default' => false,
            'is_opt_outable' => false,
        ]);

        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'user_id' => $user->id,
            'unsubscribe_token' => str_repeat('y', 64),
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        $subscriber->lists()->attach($transactional->id, [
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        // Even if someone tries to post a "subscription" key for a transactional list,
        // the component must ignore it (it only loops marketing lists).
        Livewire::actingAs($user)
            ->test(EmailPreferencesForm::class)
            ->set('subscriptions.'.$transactional->id, false)
            ->call('save')
            ->assertDispatched('saved');

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $transactional->id,
            'unsubscribed_at' => null,
        ]);
    }

    #[Test]
    public function it_global_unsubscribes_marketing_lists_but_leaves_transactional_alone(): void
    {
        $now = Carbon::parse('2026-01-04 08:00:00');
        Carbon::setTestNow($now);

        $user = User::factory()->create(['email' => 'test@example.com']);

        $marketing = EmailList::create([
            'key' => 'updates',
            'label' => 'Updates',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        $transactional = EmailList::create([
            'key' => 'receipt',
            'label' => 'Receipts',
            'purpose' => 'transactional',
            'is_default' => false,
            'is_opt_outable' => false,
        ]);

        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'user_id' => $user->id,
            'unsubscribe_token' => str_repeat('z', 64),
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        $subscriber->lists()->attach($marketing->id, [
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        $subscriber->lists()->attach($transactional->id, [
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        Livewire::actingAs($user)
            ->test(EmailPreferencesForm::class)
            ->set('optOutAllMarketing', true)
            ->call('save')
            ->assertDispatched('saved');

        $subscriber->refresh();
        $this->assertSame($now->toDateTimeString(), $subscriber->unsubscribed_at?->toDateTimeString());

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $marketing->id,
            'unsubscribed_at' => $now->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_subscriber_id' => $subscriber->id,
            'email_list_id' => $transactional->id,
            'unsubscribed_at' => null,
        ]);
    }
}
