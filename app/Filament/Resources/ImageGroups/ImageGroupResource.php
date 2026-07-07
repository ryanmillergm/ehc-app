<?php

namespace App\Filament\Resources\ImageGroups;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\ImageGroups\Pages\CreateImageGroup;
use App\Filament\Resources\ImageGroups\Pages\EditImageGroup;
use App\Filament\Resources\ImageGroups\Pages\ListImageGroups;
use App\Filament\Resources\ImageGroups\RelationManagers\ItemsRelationManager;
use App\Models\ImageGroup;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ImageGroupResource extends Resource
{
    protected static ?string $model = ImageGroup::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Image Groups';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (blank($get('slug')) && filled($state)) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->searchable()->copyable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImageGroups::route('/'),
            'create' => CreateImageGroup::route('/create'),
            'edit' => EditImageGroup::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }
}
