<?php

namespace App\Filament\Resources\EmailCampaignDeliveries\Tables;

use App\Filament\Resources\EmailCampaignDeliveries\EmailCampaignDeliveryResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailCampaignDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campaign.subject')
                    ->label('Campaign')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('to_email')
                    ->label('To')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->limit(60)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->url(fn ($record) => EmailCampaignDeliveryResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
