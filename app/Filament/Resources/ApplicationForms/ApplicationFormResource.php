<?php

namespace App\Filament\Resources\ApplicationForms;

use App\Filament\Resources\ApplicationForms\Pages\CreateApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\EditApplicationForm;
use App\Filament\Resources\ApplicationForms\Pages\ListApplicationForms;
use App\Filament\Resources\ApplicationForms\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\ApplicationForms\Schemas\ApplicationFormForm;
use App\Filament\Resources\ApplicationForms\Tables\ApplicationFormsTable;
use App\Models\ApplicationForm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApplicationFormResource extends Resource
{
    protected static ?string $model = ApplicationForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ApplicationFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApplicationFormsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListApplicationForms::route('/'),
            'create' => CreateApplicationForm::route('/create'),
            'edit'   => EditApplicationForm::route('/{record}/edit'),
        ];
    }
}
