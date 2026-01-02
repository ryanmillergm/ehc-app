<?php

namespace App\Filament\Resources\EmailCampaigns\Tables;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use App\Jobs\QueueEmailCampaignSend;
use App\Models\EmailCampaign;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class EmailCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('list.label')
                    ->label('List')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('subject')
                    ->sortable()
                    ->searchable()
                    ->limit(60),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->sortable(),

                TextColumn::make('queued_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('send')
                    ->label('Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('danger')
                    ->visible(fn (EmailCampaign $record) => $record->isSendable())
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ Send this campaign to the LIVE email list?')
                    ->modalDescription(function (EmailCampaign $record): HtmlString {
                        $campaign = $record->loadMissing('list');

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
                    ->extraModalWindowAttributes(['class' => 'send-campaign-modal'])
                    ->modalSubmitActionLabel('Yes — send it')
                    ->action(function (EmailCampaign $record): void {
                        QueueEmailCampaignSend::dispatch($record->id);

                        Notification::make()
                            ->title('Campaign queued for sending')
                            ->success()
                            ->send();
                    }),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (EmailCampaign $record) => EmailCampaignResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->icon('heroicon-o-trash')
                        ->action(fn (Collection $records) => $records->each->delete()),
                ]),
            ]);
    }
}
