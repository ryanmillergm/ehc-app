<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use App\Filament\Resources\EmailCampaigns\Pages\CreateEmailCampaign;
use App\Filament\Resources\EmailCampaigns\Pages\EditEmailCampaign;
use App\Filament\Resources\EmailCampaigns\Pages\ListEmailCampaigns;
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

class EmailCampaignResourceFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $panel = \Filament\Facades\Filament::getPanel('admin');
        if ($panel) {
            \Filament\Facades\Filament::setCurrentPanel($panel);
        }

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo([
            'admin.panel',
            'email.read',
            'email.create',
            'email.update',
            'email.delete',
        ]);

        $this->actingAs($user);
    }

    #[Test]
    public function it_can_render_the_list_page(): void
    {
        $this->get(EmailCampaignResource::getUrl('index'))
            ->assertSuccessful();
    }

    #[Test]
    public function list_page_can_see_records_in_table(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaigns = EmailCampaign::factory()
            ->count(3)
            ->create([
                'email_list_id' => $list->id,
                'status' => EmailCampaign::STATUS_DRAFT,
            ]);

        Livewire::test(ListEmailCampaigns::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($campaigns);
    }

    #[Test]
    public function create_page_creates_a_grapesjs_campaign_and_compiles_body_fields(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $designHtml = '<div class="hero"><h1>Big News</h1><p>Something beautiful.</p><a class="btn" href="https://example.com">Read more</a></div>';
        $designCss  = '.hero h1{color:#ff4d4f;} .btn{background:#111;color:#fff;padding:12px 18px;border-radius:999px;display:inline-block;text-decoration:none;}';

        Livewire::test(CreateEmailCampaign::class)
            ->fillForm([
                'email_list_id' => $list->id,
                'subject'       => 'January Blast',
                'editor'        => 'grapesjs',
                'design_html'   => $designHtml,
                'design_css'    => $designCss,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = EmailCampaign::query()->latest('id')->firstOrFail();

        $this->assertSame($list->id, $campaign->email_list_id);
        $this->assertSame('January Blast', $campaign->subject);

        $this->assertNotEmpty($campaign->body_html);
        $this->assertStringContainsString('Big News', $campaign->body_html);
        $this->assertStringNotContainsString('<style', $campaign->body_html);
        $this->assertStringNotContainsString('<html', $campaign->body_html);
        $this->assertNotEmpty($campaign->body_text);
    }

    #[Test]
    public function create_page_html_editor_accepts_full_document_and_inlines_style_blocks(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $fullDoc = '<!doctype html><html><head><meta charset="utf-8"><style>.btn{color:#fff;background:#ff4d4f;padding:10px 14px;border-radius:12px;display:inline-block;text-decoration:none;}</style></head>'
            . '<body><p>Hello</p><a class="btn" href="https://example.com">Click</a></body></html>';

        Livewire::test(CreateEmailCampaign::class)
            ->fillForm([
                'email_list_id'     => $list->id,
                'subject'           => 'HTML Mode',
                'editor'            => 'html',
                'body_html_source'  => $fullDoc,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = EmailCampaign::query()->latest('id')->firstOrFail();

        $this->assertStringContainsString('Hello', $campaign->body_html);
        $this->assertStringNotContainsString('<style', $campaign->body_html);
        $this->assertStringNotContainsString('<html', $campaign->body_html);
        $this->assertStringContainsString('style=', $campaign->body_html); // should be inlined somewhere
    }

    #[Test]
    public function edit_page_save_compiles_body_html_and_text(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => EmailCampaign::STATUS_DRAFT,
            'subject'       => 'Before',
            'editor'        => 'grapesjs',
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $campaign->getKey()])
            ->fillForm([
                'subject'     => 'After',
                'editor'      => 'grapesjs',
                'design_html' => '<h1>Updated</h1><p>Fresh content.</p>',
                'design_css'  => 'h1{color:#ff4d4f;}',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $campaign->refresh();

        $this->assertSame('After', $campaign->subject);
        $this->assertStringContainsString('Updated', $campaign->body_html);
        $this->assertNotEmpty($campaign->body_text);
    }

    #[Test]
    public function edit_page_preview_action_opens_and_contains_compiled_content(): void
    {
        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => EmailCampaign::STATUS_DRAFT,
            'subject'       => 'Preview Me',
            'editor'        => 'grapesjs',
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $campaign->getKey()])
            ->fillForm([
                'editor'      => 'grapesjs',
                'design_html' => '<h1>Big News</h1>',
                'design_css'  => 'h1{color:#ff4d4f;}',
            ])
            ->mountAction('preview')
            ->assertMountedActionModalSee('Big News');
    }

    #[Test]
    public function edit_page_send_test_action_creates_subscriber_attaches_to_list_and_sends_mail(): void
    {
        Mail::fake();

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => EmailCampaign::STATUS_DRAFT,
            'subject'       => 'Test Send',
            'editor'        => 'grapesjs',
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $campaign->getKey()])
            ->fillForm([
                'subject'     => 'Test Send',
                'editor'      => 'grapesjs',
                'design_html' => '<h1>Hello</h1><p>World</p>',
                'design_css'  => 'h1{color:#ff4d4f;}',
            ])
            ->mountAction('sendTest')
            ->fillForm([
                'email' => 'person@example.com',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $subscriber = EmailSubscriber::query()
            ->where('email', 'person@example.com')
            ->firstOrFail();

        $this->assertDatabaseHas('email_list_subscriber', [
            'email_list_id' => $list->id,
            'email_subscriber_id' => $subscriber->id,
        ]);

        Mail::assertSent(EmailCampaignMail::class, function (EmailCampaignMail $mail) {
            return $mail->hasTo('person@example.com');
        });
    }

    #[Test]
    public function edit_page_send_campaign_action_dispatches_queue_job_and_is_disabled_when_not_sendable(): void
    {
        Bus::fake();

        $list = EmailList::factory()->create(['purpose' => 'marketing']);

        $draft = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => EmailCampaign::STATUS_DRAFT,
            'subject'       => 'Go Live',
            'editor'        => 'grapesjs',
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $draft->getKey()])
            ->assertActionEnabled('sendCampaign')
            ->fillForm([
                'editor'      => 'grapesjs',
                'design_html' => '<h1>Live</h1>',
                'design_css'  => 'h1{color:#ff4d4f;}',
            ])
            ->mountAction('sendCampaign')
            ->callMountedAction()
            ->assertHasNoFormErrors();

        Bus::assertDispatched(QueueEmailCampaignSend::class);

        $sent = EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => EmailCampaign::STATUS_SENT,
            'subject'       => 'Already sent',
        ]);

        Livewire::test(EditEmailCampaign::class, ['record' => $sent->getKey()])
            ->assertActionDisabled('sendCampaign');
    }

    #[Test]
    public function send_campaign_action_saves_and_compiles_latest_form_state_before_queueing(): void
    {
        \Illuminate\Support\Facades\Bus::fake();

        $list = \App\Models\EmailList::factory()->create(['purpose' => 'marketing']);

        $campaign = \App\Models\EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
            'status'        => \App\Models\EmailCampaign::STATUS_DRAFT,
            'subject'       => 'Original Subject',
            'editor'        => 'grapesjs',
            'body_html'     => '<p>OLD HTML</p>',
            'body_text'     => 'OLD HTML',
            'design_html'   => '<p>OLD HTML</p>',
            'design_css'    => '',
        ]);

        // Start editing, but DO NOT click Save.
        $lw = \Livewire\Livewire::test(\App\Filament\Resources\EmailCampaigns\Pages\EditEmailCampaign::class, [
            'record' => $campaign->getKey(),
        ])->fillForm([
            'email_list_id' => $list->id,
            'subject'       => 'Original Subject',
            'editor'        => 'grapesjs',
            'design_html'   => '<h1 class="title">New Headline</h1><p>New copy</p><a class="btn" href="https://example.com">Go</a>',
            'design_css'    => '.title{color:#ff4d4f;} .btn{background:#111;color:#fff;padding:10px 14px;border-radius:999px;display:inline-block;text-decoration:none;}',
        ]);

        // Prove the DB did NOT change just because the form state changed.
        $campaign->refresh();
        $this->assertStringContainsString('OLD HTML', $campaign->body_html);

        // Now click "Send (LIVE)" (this should compile + persist + dispatch).
        $lw->mountAction('sendCampaign')
            ->callMountedAction()
            ->assertHasNoFormErrors();

        // The record should have been updated with compiled HTML/text.
        $campaign->refresh();

        $this->assertStringContainsString('New Headline', $campaign->body_html);
        $this->assertStringNotContainsString('OLD HTML', $campaign->body_html);
        $this->assertStringNotContainsString('<style', $campaign->body_html);
        $this->assertNotEmpty($campaign->body_text);
        $this->assertStringContainsString('New Headline', $campaign->body_text);

        // And the queue job should have been dispatched.
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\QueueEmailCampaignSend::class);
    }
}
