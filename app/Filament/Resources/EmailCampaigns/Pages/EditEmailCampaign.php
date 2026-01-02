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
use Illuminate\Support\Str;

class EditEmailCampaign extends EditRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test')
                ->schema([
                    TextInput::make('email')
                        ->label('Send test to')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $campaign = $this->record->loadMissing('list');

                    if (! $campaign->list) {
                        Notification::make()
                            ->title('No email list selected')
                            ->danger()
                            ->send();
                        return;
                    }

                    $rawEmail = trim(strtolower($data['email']));
                    $canonical = EmailCanonicalizer::canonicalize($rawEmail) ?? $rawEmail;

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
                        // ensure they are globally opted-in for marketing (for realistic testing)
                        $subscriber->update([
                            'email' => $canonical,
                            'unsubscribed_at' => null,
                            'subscribed_at' => $subscriber->subscribed_at ?? now(),
                        ]);
                    }

                    // ensure list pivot is active (so unsubscribeThisUrl + “real send rules” match)
                    $campaign->list->subscribers()->syncWithoutDetaching([
                        $subscriber->id => [
                            'subscribed_at' => now(),
                            'unsubscribed_at' => null,
                        ],
                    ]);

                    Mail::to($subscriber->email, $subscriber->name)->send(
                        new EmailCampaignMail(
                            subscriber: $subscriber,
                            list: $campaign->list,
                            subjectLine: $campaign->subject,
                            bodyHtml: $campaign->body_html,
                        )
                    );

                    Notification::make()
                        ->title('Test email sent')
                        ->success()
                        ->send();
                }),

            Action::make('sendCampaign')
                ->label('Send campaign')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->isSendable())
                ->action(function (): void {
                    QueueEmailCampaignSend::dispatch($this->record->id);

                    Notification::make()
                        ->title('Campaign queued for sending')
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
