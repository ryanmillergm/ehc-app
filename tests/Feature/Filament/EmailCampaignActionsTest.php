<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EmailCampaigns\Pages\EditEmailCampaign;
use App\Jobs\QueueEmailCampaignSend;
use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailCampaignActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ✅ Include email.delete so policy checks don't explode during action rendering
        foreach (['email.read', 'email.create', 'email.update', 'email.delete'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // If you have a panel id other than 'admin', change it here.
        $panel = Filament::getPanel('admin');
        if ($panel) {
            Filament::setCurrentPanel($panel);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['email.read', 'email.update']); // no delete needed

        $this->actingAs($user);
    }

    #[Test]
    public function it_can_send_a_test_email_from_the_edit_page(): void
    {
        Mail::fake();

        $list = EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => false,
            'is_opt_outable' => true,
        ]);

        $campaign = EmailCampaign::create([
            'email_list_id' => $list->id,
            'subject' => 'Hello!',
            'body_html' => '<p>Test body</p>',
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $campaign->getKey()])
            ->callAction('sendTest', data: ['email' => 'me@example.com'])
            ->assertHasNoFormErrors();

        Mail::assertSent(EmailCampaignMail::class);
    }

    #[Test]
    public function it_queues_a_campaign_send_from_the_edit_page(): void
    {
        Bus::fake();

        $list = EmailList::create([
            'key' => 'newsletter',
            'label' => 'Newsletter',
            'purpose' => 'marketing',
            'is_default' => false,
            'is_opt_outable' => true,
        ]);

        $active = EmailSubscriber::create([
            'email' => 'active@example.com',
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at' => now(),
        ]);

        $inactive = EmailSubscriber::create([
            'email' => 'inactive@example.com',
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'subscribed_at' => now(),
            'unsubscribed_at' => now(),
        ]);

        $list->subscribers()->attach($active->id, ['unsubscribed_at' => null]);
        $list->subscribers()->attach($inactive->id, ['unsubscribed_at' => null]);

        $campaign = EmailCampaign::create([
            'email_list_id' => $list->id,
            'subject' => 'Live send',
            'body_html' => '<p>Go!</p>',
            'status' => EmailCampaign::STATUS_DRAFT,
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $campaign->getKey()])
            ->callAction('sendCampaign')
            ->assertHasNoFormErrors();

        Bus::assertDispatched(
            QueueEmailCampaignSend::class,
            fn ($job) => $job->campaignId === $campaign->id
        );
    }
}
