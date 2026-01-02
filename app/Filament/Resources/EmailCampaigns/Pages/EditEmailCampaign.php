<?php

namespace App\Filament\Resources\EmailCampaigns\Pages;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use App\Jobs\QueueEmailCampaignSend;
use App\Mail\EmailCampaignMail;
use App\Models\EmailSubscriber;
use App\Support\EmailCanonicalizer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EditEmailCampaign extends EditRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test')
                ->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('email')
                        ->label('Send test to')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $campaign = $this->getRecord();
                    $list = $campaign->list; // your EmailCampaign has list()

                    if (! $list) {
                        Notification::make()
                            ->title('No list selected')
                            ->danger()
                            ->send();

                        return;
                    }

                    $email = trim(strtolower($data['email']));
                    $canonical = EmailCanonicalizer::canonicalize($email) ?? $email;

                    $subscriber = EmailSubscriber::query()
                        ->where('email_canonical', $canonical)
                        ->orWhere('email', $canonical)
                        ->first();

                    if (! $subscriber) {
                        $subscriber = EmailSubscriber::create([
                            'email' => $canonical,
                            'unsubscribe_token' => Str::random(64),
                            'subscribed_at' => now(),
                            'preferences' => [],
                        ]);
                    } else {
                        // ensure globally opted-in so links + behavior match real sends
                        $subscriber->update([
                            'unsubscribed_at' => null,
                            'subscribed_at' => $subscriber->subscribed_at ?? now(),
                        ]);
                    }

                    // ensure they’re subscribed to THIS list for accurate “unsubscribe this list” behavior
                    $list->subscribers()->syncWithoutDetaching([
                        $subscriber->id => [
                            'subscribed_at' => now(),
                            'unsubscribed_at' => null,
                        ],
                    ]);

                    Mail::to($subscriber->email)->send(new EmailCampaignMail(
                        subscriber: $subscriber,
                        list: $list,
                        subjectLine: $campaign->subject,
                        bodyHtml: $campaign->body_html,
                    ));

                    Notification::make()
                        ->title('Test email sent')
                        ->success()
                        ->send();
                }),

                Action::make('sendCampaign')
                    ->label('Send (LIVE)')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('danger')
                    ->disabled(fn () => ! $this->getRecord()->isSendable())
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ Send this campaign to the LIVE email list?')
                    ->modalDescription(function (): HtmlString {
                        $campaign = $this->getRecord()->loadMissing('list');

                        if (! $campaign->list) {
                            return new HtmlString('<div class="whitespace-pre-line">No list is selected. Pick an Email List first.</div>');
                        }

                        $active = $campaign->list->subscribers()
                            ->whereNull('email_list_subscriber.unsubscribed_at')
                            ->count();

                        $text = implode("\n", [
                            'You are about to send a LIVE email blast.',
                            "List: {$campaign->list->label} ({$campaign->list->key})",
                            "Active recipients: {$active}",
                            "Subject: {$campaign->subject}",
                            'Tip: Send a TEST email first to verify formatting/links.',
                            'This action queues emails immediately and cannot be undone.',
                        ]);

                        return new HtmlString('<div class="whitespace-pre-line">' . e($text) . '</div>');
                    })
                    ->modalSubmitActionLabel('Yes — send it')
                    ->extraModalWindowAttributes(['class' => 'send-campaign-modal'])
                    ->action(function (): void {
                        $campaign = $this->getRecord();

                        QueueEmailCampaignSend::dispatch($campaign->id);

                        Notification::make()
                            ->title('Campaign queued for sending')
                            ->success()
                            ->send();
                    }),

            DeleteAction::make(),
        ];
    }
}
