<?php

namespace App\Filament\Resources\VolunteerApplications;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\VolunteerApplications\Pages\EditVolunteerApplication;
use App\Filament\Resources\VolunteerApplications\Pages\ListVolunteerApplications;
use App\Filament\Resources\VolunteerApplications\Schemas\VolunteerApplicationForm;
use App\Filament\Resources\VolunteerApplications\Tables\VolunteerApplicationsTable;
use App\Models\VolunteerApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VolunteerApplicationResource extends Resource
{
    protected static ?string $model = VolunteerApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::UserSettings;
    protected static ?string $navigationLabel = 'Volunteer Applications';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return VolunteerApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VolunteerApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVolunteerApplications::route('/'),
            'edit'  => EditVolunteerApplication::route('/{record}/edit'),
        ];
    }
}
