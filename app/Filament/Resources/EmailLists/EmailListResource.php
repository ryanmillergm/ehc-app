<?php

namespace App\Filament\Resources\EmailLists;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\EmailLists\Pages\CreateEmailList;
use App\Filament\Resources\EmailLists\Pages\EditEmailList;
use App\Filament\Resources\EmailLists\Pages\ListEmailLists;
use App\Filament\Resources\EmailLists\RelationManagers\SubscribersRelationManager;
use App\Filament\Resources\EmailLists\Schemas\EmailListForm;
use App\Filament\Resources\EmailLists\Tables\EmailListsTable;
use App\Models\EmailList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailListResource extends Resource
{
    protected static ?string $model = EmailList::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Email;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;
    protected static ?string $navigationLabel = 'Email Lists';
    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return EmailListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubscribersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailLists::route('/'),
            'create' => CreateEmailList::route('/create'),
            'edit' => EditEmailList::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'campaigns',
                'subscribers as active_subscribers_count' => fn ($q) =>
                    $q->whereNull('email_list_subscriber.unsubscribed_at'),
            ]);
    }
}
