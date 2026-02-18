<?php

namespace App\Filament\Resources\HomeSections;

use App\Enums\HomeSectionKey;
use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\HomeSections\Pages\CreateHomeSection;
use App\Filament\Resources\HomeSections\Pages\EditHomeSection;
use App\Filament\Resources\HomeSections\Pages\ListHomeSections;
use App\Filament\Resources\HomeSections\RelationManagers\ItemsRelationManager;
use App\Models\HomeSection;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomeSectionResource extends Resource
{
    protected static ?string $model = HomeSection::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Pages;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationLabel = 'Home Sections';
    protected static ?string $recordTitleAttribute = 'section_key';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('language_id')
                ->relationship('language', 'title')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('section_key')
                ->options(collect(HomeSectionKey::cases())->mapWithKeys(
                    fn (HomeSectionKey $key) => [$key->value => $key->label()]
                )->all())
                ->required()
                ->helperText('Choose which home page section this row powers.'),
            TextInput::make('eyebrow')
                ->maxLength(255),
            TextInput::make('heading')
                ->maxLength(255),
            TextInput::make('subheading')
                ->maxLength(255),
            Textarea::make('body')
                ->rows(4)
                ->columnSpanFull(),
            Textarea::make('note')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('cta_primary_label')
                ->maxLength(255),
            TextInput::make('cta_primary_url')
                ->maxLength(255),
            TextInput::make('cta_secondary_label')
                ->maxLength(255),
            TextInput::make('cta_secondary_url')
                ->maxLength(255),
            TextInput::make('cta_tertiary_label')
                ->maxLength(255),
            TextInput::make('cta_tertiary_url')
                ->maxLength(255),
            Select::make('image_id')
                ->relationship('image', 'path')
                ->searchable()
                ->preload()
                ->nullable(),
            KeyValue::make('meta')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('language.title')->label('Language')->sortable(),
                TextColumn::make('section_key')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => HomeSectionKey::tryFrom($state)?->label() ?? $state),
                TextColumn::make('heading')->limit(50),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeSections::route('/'),
            'create' => CreateHomeSection::route('/create'),
            'edit' => EditHomeSection::route('/{record}/edit'),
        ];
    }
}
