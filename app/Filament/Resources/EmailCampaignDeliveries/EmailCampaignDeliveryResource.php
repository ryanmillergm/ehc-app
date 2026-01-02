<?php

namespace App\Filament\Resources\EmailCampaignDeliveries;

use App\Filament\Resources\EmailCampaignDeliveries\Pages\ListEmailCampaignDeliveries;
use App\Filament\Resources\EmailCampaignDeliveries\Pages\ViewEmailCampaignDelivery;
use App\Filament\Resources\EmailCampaignDeliveries\Tables\EmailCampaignDeliveriesTable;
use App\Models\EmailCampaignDelivery;
use BackedEnum;
use UnitEnum;
use Filament\Infolists; // ✅ IMPORTANT
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmailCampaignDeliveryResource extends Resource
{
    protected static ?string $model = EmailCampaignDelivery::class;

    protected static string|UnitEnum|null $navigationGroup = 'Email';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;
    protected static ?string $navigationLabel = 'Email Deliveries';
    protected static ?string $recordTitleAttribute = 'subject';

    public static function table(Table $table): Table
    {
        return EmailCampaignDeliveriesTable::configure($table);
    }

    // Filament v4 uses Schema here
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Infolists\Components\Section::make('Delivery')
                ->schema([
                    Infolists\Components\TextEntry::make('campaign.subject')->label('Campaign Subject'),
                    Infolists\Components\TextEntry::make('campaign.list.label')->label('List'),
                    Infolists\Components\TextEntry::make('status')->badge(),

                    Infolists\Components\TextEntry::make('to_email')->label('To'),
                    Infolists\Components\TextEntry::make('to_name')->label('To Name')->placeholder('-'),

                    Infolists\Components\TextEntry::make('from_email')->label('From')->placeholder('-'),
                    Infolists\Components\TextEntry::make('from_name')->label('From Name')->placeholder('-'),

                    Infolists\Components\TextEntry::make('subject')->label('Subject'),
                    Infolists\Components\TextEntry::make('attempts')->label('Attempts'),

                    Infolists\Components\TextEntry::make('sent_at')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('failed_at')->dateTime()->placeholder('-'),
                    Infolists\Components\TextEntry::make('last_error')->columnSpanFull()->placeholder('-'),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Rendered HTML')
                ->schema([
                    Infolists\Components\TextEntry::make('body_html')
                        ->label('')
                        ->html()
                        ->columnSpanFull()
                        ->placeholder('No HTML stored. (Not sent yet?)'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailCampaignDeliveries::route('/'),
            'view'  => ViewEmailCampaignDelivery::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
