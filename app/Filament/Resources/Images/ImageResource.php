<?php

namespace App\Filament\Resources\Images;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\Images\Pages\CreateImage;
use App\Filament\Resources\Images\Pages\EditImage;
use App\Filament\Resources\Images\Pages\ListImages;
use App\Models\Image;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImageResource extends Resource
{
    protected static ?string $model = Image::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Images';
    protected static ?string $recordTitleAttribute = 'path';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Image File')
                ->image()
                ->directory('cms/images/' . now()->format('Y/m'))
                ->disk('public')
                ->visibility('public')
                ->required()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (blank($state)) {
                        return;
                    }

                    $set('public_url', url('/storage/' . ltrim((string) $state, '/')));
                    $set('extension', pathinfo((string) $state, PATHINFO_EXTENSION));
                    $set('mime_type', null);
                }),
            Hidden::make('disk')
                ->default('public')
                ->dehydrateStateUsing(static fn () => 'public')
                ->dehydrated(true),
            TextInput::make('public_url')
                ->maxLength(255)
                ->helperText('Copy this URL for content or embeds.'),
            Select::make('image_type_id')
                ->label('Image Type')
                ->relationship('type', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            TextInput::make('title')
                ->maxLength(255),
            Toggle::make('is_decorative')
                ->default(false)
                ->helperText('Decorative images should use empty alt text in rendered markup.'),
            TextInput::make('alt_text')
                ->maxLength(255)
                ->required(fn (callable $get) => ! (bool) $get('is_decorative')),
            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('caption')
                ->maxLength(255),
            TextInput::make('credit')
                ->maxLength(255),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('path')->label('Preview')->disk('public')->square(),
                TextColumn::make('path')->searchable()->copyable(),
                TextColumn::make('public_url')->copyable()->toggleable(),
                TextColumn::make('type.name')->label('Type')->sortable(),
                TextColumn::make('title')->searchable()->limit(30),
                TextColumn::make('alt_text')->searchable()->limit(40),
                IconColumn::make('is_decorative')->boolean(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImages::route('/'),
            'create' => CreateImage::route('/create'),
            'edit' => EditImage::route('/{record}/edit'),
        ];
    }
}
