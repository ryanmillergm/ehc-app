<?php

namespace App\Filament\Resources\ImageGroupables;

use App\Enums\Media\ImageAttachableType;
use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\ImageGroupables\Pages\CreateImageGroupable;
use App\Filament\Resources\ImageGroupables\Pages\EditImageGroupable;
use App\Filament\Resources\ImageGroupables\Pages\ListImageGroupables;
use App\Models\ImageGroupable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImageGroupableResource extends Resource
{
    protected static ?string $model = ImageGroupable::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Image Group Relationships';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('image_group_id')
                ->relationship('group', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('image_groupable_type')
                ->options(ImageAttachableType::options())
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('image_groupable_id', null);
                })
                ->rules(['in:' . implode(',', array_keys(ImageAttachableType::options()))])
                ->required(),
            Select::make('image_groupable_id')
                ->label('Related Record')
                ->options(fn (callable $get): array => ImageAttachableType::relatedRecordOptions($get('image_groupable_type')))
                ->searchable()
                ->preload()
                ->disabled(fn (callable $get): bool => blank($get('image_groupable_type')))
                ->helperText(fn (callable $get): ?string => blank($get('image_groupable_type')) ? 'Select a target type first.' : null)
                ->required()
                ->rules(['integer', 'min:1'])
                ->rule(function (callable $get) {
                    return function (string $attribute, $value, \Closure $fail) use ($get): void {
                        $type = $get('image_groupable_type');
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
                    'gallery' => 'Gallery',
                    'carousel' => 'Carousel',
                ])
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
                TextColumn::make('group.name')->label('Group')->searchable(),
                TextColumn::make('image_groupable_type')
                    ->label('Related Type')
                    ->formatStateUsing(fn (?string $state): ?string => ImageAttachableType::labelFor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('image_groupable_id')->label('Related ID')->sortable(),
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
            'index' => ListImageGroupables::route('/'),
            'create' => CreateImageGroupable::route('/create'),
            'edit' => EditImageGroupable::route('/{record}/edit'),
        ];
    }
}
