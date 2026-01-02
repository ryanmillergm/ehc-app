<?php

namespace App\Filament\Resources\EmailCampaigns\Tables;

use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use App\Jobs\QueueEmailCampaignSend;
use App\Jobs\SendEmailCampaign;
use App\Models\EmailCampaign;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                    ->requiresConfirmation()
                    ->visible(fn (EmailCampaign $record) => $record->isSendable())
                    ->action(fn (EmailCampaign $record) => QueueEmailCampaignSend::dispatch($record->id)),

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
