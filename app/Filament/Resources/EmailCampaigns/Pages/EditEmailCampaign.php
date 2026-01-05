<?php

namespace App\Filament\Resources\EmailCampaigns\Pages;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use App\Jobs\QueueEmailCampaignSend;
use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailSubscriber;
use App\Support\Email\EmailBodyCompiler;
use App\Support\EmailCanonicalizer;
use App\Support\HtmlFragments;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EditEmailCampaign extends EditRecord
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->modalHeading('Email Preview')
                ->modalWidth('7xl')
                ->modalContent(function () {
                    $state = $this->form->getState();
                    [$html] = $this->compileBodyFromEditorState($state);

                    $srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
                        . '<body style="margin:0;padding:0;">' . $html . '</body></html>';

                    return view('filament.email-campaigns.preview', [
                        'srcdoc' => e($srcdoc),
                    ]);
                }),

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
                    $campaign = $this->getRecord()->loadMissing('list');
                    $list = $campaign->list;

                    if (! $list) {
                        Notification::make()->title('No list selected')->danger()->send();
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
                        $subscriber->update([
                            'unsubscribed_at' => null,
                            'subscribed_at' => $subscriber->subscribed_at ?? now(),
                        ]);
                    }

                    $list->subscribers()->syncWithoutDetaching([
                        $subscriber->id => [
                            'subscribed_at' => now(),
                            'unsubscribed_at' => null,
                        ],
                    ]);

                    $state = $this->form->getState();
                    [$bodyHtml] = $this->compileBodyFromEditorState($state);

                    Mail::to($subscriber->email)->send(new EmailCampaignMail(
                        subscriber: $subscriber,
                        list: $list,
                        subjectLine: (string) ($state['subject'] ?? $campaign->subject),
                        bodyHtml: $bodyHtml,
                    ));

                    Notification::make()->title('Test email sent')->success()->send();
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
                    // Ensure latest form state is saved/compiled before queueing.
                    $data = $this->form->getState();
                    $data = $this->mutateFormDataBeforeSave($data);
                    $this->getRecord()->update($data);

                    QueueEmailCampaignSend::dispatch($this->getRecord()->id);

                    Notification::make()->title('Campaign queued for sending')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$bodyHtml, $bodyText] = $this->compileBodyFromEditorState($data);

        $data['body_html'] = $bodyHtml;
        $data['body_text'] = $bodyText;

        unset($data['body_html_source']); // not a DB column

        return $data;
    }

    private function compileBodyFromEditorState(array $state): array
    {
        $editor = $state['editor'] ?? 'grapesjs';

        if ($editor === 'grapesjs') {
            $compiled = app(EmailBodyCompiler::class)->compile(
                (string) ($state['design_html'] ?? ''),
                (string) ($state['design_css'] ?? ''),
            );

            return [$compiled['html'], $compiled['text']];
        }

        if ($editor === 'html') {
            $raw = (string) ($state['body_html_source'] ?? $state['body_html'] ?? '');
            return $this->compileFromPossiblyFullDocument($raw);
        }

        // rich
        $html = HtmlFragments::bodyInner((string) ($state['body_html'] ?? ''));
        return [$html, self::toText($html)];
    }

    private function compileFromPossiblyFullDocument(string $raw): array
    {
        $css = '';
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $raw, $m)) {
            $css = implode("\n", $m[1]);
            $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $raw);
        }

        $html = HtmlFragments::bodyInner($raw);

        $compiled = app(EmailBodyCompiler::class)->compile($html, $css);

        return [$compiled['html'], $compiled['text']];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    private static function toText(?string $html): ?string
    {
        if (! filled($html)) return null;

        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit(trim($text), 10000, '');
    }
}
