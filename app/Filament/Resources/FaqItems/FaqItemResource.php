<?php

namespace App\Filament\Resources\FaqItems;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\FaqItems\Pages\CreateFaqItem;
use App\Filament\Resources\FaqItems\Pages\EditFaqItem;
use App\Filament\Resources\FaqItems\Pages\ListFaqItems;
use App\Models\FaqItem;
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

class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Pages;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel = 'FAQ Items';
    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('context')
                ->default('home')
                ->required()
                ->maxLength(60),
            Select::make('language_id')
                ->relationship('language', 'title')
                ->searchable()
                ->preload()
                ->nullable(),
            TextInput::make('question')
                ->required()
                ->maxLength(255),
            Textarea::make('answer')
                ->required()
                ->rows(4)
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
            ->columns([
                TextColumn::make('context')->sortable(),
                TextColumn::make('language.title')->label('Language')->sortable(),
                TextColumn::make('question')->searchable()->limit(80),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->defaultSort('id', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqItems::route('/'),
            'create' => CreateFaqItem::route('/create'),
            'edit' => EditFaqItem::route('/{record}/edit'),
        ];
    }
}
