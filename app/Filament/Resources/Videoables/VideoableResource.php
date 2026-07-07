<?php

namespace App\Filament\Resources\Videoables;

use App\Enums\Media\VideoAttachableType;
use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\Videoables\Pages\CreateVideoable;
use App\Filament\Resources\Videoables\Pages\EditVideoable;
use App\Filament\Resources\Videoables\Pages\ListVideoables;
use App\Models\Videoable;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideoableResource extends Resource
{
    protected static ?string $model = Videoable::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Images;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationLabel = 'Video Relationships';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('video_id')
                ->relationship('video', 'title')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('videoable_type')
                ->options(VideoAttachableType::options())
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('videoable_id', null);
                })
                ->rules(['in:' . implode(',', array_keys(VideoAttachableType::options()))])
                ->required(),
            Select::make('videoable_id')
                ->label('Related Record')
                ->options(fn (callable $get): array => VideoAttachableType::relatedRecordOptions($get('videoable_type')))
                ->searchable()
                ->preload()
                ->disabled(fn (callable $get): bool => blank($get('videoable_type')))
                ->helperText(fn (callable $get): ?string => blank($get('videoable_type')) ? 'Select a target type first.' : null)
                ->required()
                ->rules(['integer', 'min:1'])
                ->rule(function (callable $get) {
                    return function (string $attribute, $value, \Closure $fail) use ($get): void {
                        $type = $get('videoable_type');
                        $id = (int) $value;

                        if ($id < 1) {
                            $fail('The selected related record is invalid.');

                            return;
                        }

                        if (! VideoAttachableType::targetExists($type, $id)) {
                            $fail('The selected related record is invalid.');
                        }
                    };
                }),
            Select::make('role')
                ->options([
                    'hero_video' => 'Hero Video',
                    'featured_video' => 'Featured Video',
                    'inline_video' => 'Inline Video',
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
                TextColumn::make('video.title')->label('Video')->searchable()->limit(60),
                TextColumn::make('videoable_type')
                    ->label('Related Type')
                    ->formatStateUsing(fn (?string $state): ?string => VideoAttachableType::labelFor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('videoable_id')->label('Related ID')->sortable(),
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
            'index' => ListVideoables::route('/'),
            'create' => CreateVideoable::route('/create'),
            'edit' => EditVideoable::route('/{record}/edit'),
        ];
    }
}
