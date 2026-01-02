<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroup;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\PledgeResource\Pages\ManagePledges;
use App\Filament\Resources\PledgeResource\Pages;
use App\Filament\Resources\PledgeResource\RelationManagers;
use App\Models\Pledge;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PledgeResource extends Resource
{
    protected static ?string $model = Pledge::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::CurrencyDollar;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Donations;
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePledges::route('/'),
        ];
    }
}
