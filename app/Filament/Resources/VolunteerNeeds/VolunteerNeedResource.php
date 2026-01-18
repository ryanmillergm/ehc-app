<?php

namespace App\Filament\Resources\VolunteerNeeds;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\VolunteerNeeds\Pages\CreateVolunteerNeed;
use App\Filament\Resources\VolunteerNeeds\Pages\EditVolunteerNeed;
use App\Filament\Resources\VolunteerNeeds\Pages\ListVolunteerNeeds;
use App\Filament\Resources\VolunteerNeeds\Schemas\VolunteerNeedForm;
use App\Filament\Resources\VolunteerNeeds\Tables\VolunteerNeedsTable;
use App\Models\VolunteerNeed;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VolunteerNeedResource extends Resource
{
    protected static ?string $model = VolunteerNeed::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::UserSettings;
    protected static ?string $navigationLabel = 'Volunteer Needs';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return VolunteerNeedForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VolunteerNeedsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListVolunteerNeeds::route('/'),
            'create' => CreateVolunteerNeed::route('/create'),
            'edit'   => EditVolunteerNeed::route('/{record}/edit'),
        ];
    }
}
