<?php

namespace App\Filament\Resources\Imageables;

use App\Enums\Media\ImageAttachableType;
use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\Imageables\Pages\CreateImageable;
use App\Filament\Resources\Imageables\Pages\EditImageable;
use App\Filament\Resources\Imageables\Pages\ListImageables;
use App\Models\Imageable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImageableResource extends Resource
{
    protected static ?string $model = Imageable::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Image Relationships';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('image_id')
                ->relationship('image', 'path')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('imageable_type')
                ->options(ImageAttachableType::options())
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('imageable_id', null);
                })
                ->rules(['in:' . implode(',', array_keys(ImageAttachableType::options()))])
                ->required(),
            Select::make('imageable_id')
                ->label('Related Record')
                ->options(fn (callable $get): array => ImageAttachableType::relatedRecordOptions($get('imageable_type')))
                ->searchable()
                ->preload()
                ->disabled(fn (callable $get): bool => blank($get('imageable_type')))
                ->helperText(fn (callable $get): ?string => blank($get('imageable_type')) ? 'Select a target type first.' : null)
                ->required()
                ->rules(['integer', 'min:1'])
                ->rule(function (callable $get) {
                    return function (string $attribute, $value, \Closure $fail) use ($get): void {
                        $type = $get('imageable_type');
                        $id = (int) $value;

                        if ($id < 1) {
                            $fail('The selected related record is invalid.');

                            return;
                        }

                        if (! ImageAttachableType::targetExists($type, $id)) {
                            $fail('The selected related record is invalid.');
                        }
                    };
                }),
            Select::make('role')
                ->options([
                    'header' => 'Header',
                    'featured' => 'Featured',
                    'og' => 'OG',
                    'thumbnail' => 'Thumbnail',
                ])
                ->helperText('Use "OG" for the Open Graph social preview image used by Facebook, LinkedIn, and X.')
                ->nullable(),
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
                TextColumn::make('image.path')->label('Image')->searchable()->limit(60),
                TextColumn::make('imageable_type')
                    ->label('Related Type')
                    ->formatStateUsing(fn (?string $state): ?string => ImageAttachableType::labelFor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('imageable_id')->label('Related ID')->sortable(),
                TextColumn::make('role')->sortable(),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImageables::route('/'),
            'create' => CreateImageable::route('/create'),
            'edit' => EditImageable::route('/{record}/edit'),
        ];
    }
}
