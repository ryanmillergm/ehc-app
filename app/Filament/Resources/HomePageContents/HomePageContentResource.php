<?php

namespace App\Filament\Resources\HomePageContents;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\HomePageContents\Pages\CreateHomePageContent;
use App\Filament\Resources\HomePageContents\Pages\EditHomePageContent;
use App\Filament\Resources\HomePageContents\Pages\ListHomePageContents;
use App\Models\HomePageContent;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomePageContentResource extends Resource
{
    protected static ?string $model = HomePageContent::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Pages;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Home Page Content';
    protected static ?string $recordTitleAttribute = 'hero_intro';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('language_id')
                ->relationship('language', 'title')
                ->searchable()
                ->preload()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('seo_title')
                ->maxLength(255),
            Textarea::make('seo_description')
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('hero_intro')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('meeting_schedule')
                ->maxLength(255),
            TextInput::make('meeting_location')
                ->maxLength(255),
            Select::make('hero_image_id')
                ->relationship('heroImage', 'path')
                ->searchable()
                ->preload()
                ->nullable(),
            Select::make('featured_image_id')
                ->relationship('featuredImage', 'path')
                ->searchable()
                ->preload()
                ->nullable(),
            Select::make('og_image_id')
                ->relationship('ogImage', 'path')
                ->searchable()
                ->preload()
                ->nullable(),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('language.title')->label('Language')->sortable(),
                TextColumn::make('seo_title')->label('SEO Title')->limit(60),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomePageContents::route('/'),
            'create' => CreateHomePageContent::route('/create'),
            'edit' => EditHomePageContent::route('/{record}/edit'),
        ];
    }
}
