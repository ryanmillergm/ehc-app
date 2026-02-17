<?php

namespace App\Filament\Resources\ImageGroupItems;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\ImageGroupItems\Pages\CreateImageGroupItem;
use App\Filament\Resources\ImageGroupItems\Pages\EditImageGroupItem;
use App\Filament\Resources\ImageGroupItems\Pages\ListImageGroupItems;
use App\Models\ImageGroupItem;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImageGroupItemResource extends Resource
{
    protected static ?string $model = ImageGroupItem::class;
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Image Group Items';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('image_group_id')
                ->relationship('group', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('image_id')
                ->relationship('image', 'path')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group.name')->label('Group')->sortable()->searchable(),
                TextColumn::make('image.path')->label('Image')->limit(60)->searchable(),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImageGroupItems::route('/'),
            'create' => CreateImageGroupItem::route('/create'),
            'edit' => EditImageGroupItem::route('/{record}/edit'),
        ];
    }
}
