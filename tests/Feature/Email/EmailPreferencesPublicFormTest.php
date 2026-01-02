<?php

namespace Tests\Feature\Email;

use App\Livewire\EmailPreferencesPublicForm;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailPreferencesPublicFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_saves_marketing_list_opt_out_by_token(): void
    {
        $now = Carbon::parse('2026-01-10 10:00:00');
        Carbon::setTestNow($now);

        $list = EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'unsubscribe_token' => str_repeat('u', 64),
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        $subscriber->lists()->attach($list->id, [
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        Livewire::test(EmailPreferencesPublicForm::class, ['token' => $subscriber->unsubscribe_token])
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
    public function it_global_unsubscribes_marketing_by_token(): void
    {
        $now = Carbon::parse('2026-01-11 11:00:00');
        Carbon::setTestNow($now);

        $marketing = EmailList::create([
            'key' => 'updates',
            'label' => 'Updates',
            'purpose' => 'marketing',
            'is_default' => true,
            'is_opt_outable' => true,
        ]);

        $subscriber = EmailSubscriber::create([
            'email' => 'test@example.com',
            'unsubscribe_token' => str_repeat('v', 64),
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        $subscriber->lists()->attach($marketing->id, [
            'subscribed_at' => $now,
            'unsubscribed_at' => null,
        ]);

        Livewire::test(EmailPreferencesPublicForm::class, ['token' => $subscriber->unsubscribe_token])
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
    }
}
