<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroup;
use App\Filament\Resources\RouteSeoResource\Pages\CreateRouteSeo;
use App\Filament\Resources\RouteSeoResource\Pages\EditRouteSeo;
use App\Filament\Resources\RouteSeoResource\Pages\ListRouteSeos;
use App\Filament\Resources\RouteSeoResource\Pages\ViewRouteSeo;
use App\Models\SeoMeta;
use App\Support\Seo\RouteSeoTarget;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RouteSeoResource extends Resource
{
    protected static ?string $model = SeoMeta::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::MagnifyingGlassCircle;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Pages;
    protected static ?string $navigationLabel = 'Route SEO';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('target_key')
                ->label('Route')
                ->options(RouteSeoTarget::options())
                ->required(),
            Select::make('language_id')
                ->label('Language')
                ->relationship('language', 'title')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('seo_title')
                ->maxLength(255),
            Textarea::make('seo_description')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('seo_og_image')
                ->label('SEO OG Image URL')
                ->maxLength(500),
            TextInput::make('canonical_path')
                ->helperText('Optional path override (example: /give).')
                ->maxLength(255),
            Toggle::make('is_active')
                ->default(true)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('target_key')
                    ->label('Route')
                    ->state(fn (SeoMeta $record): string => RouteSeoTarget::options()[$record->target_key] ?? $record->target_key),
                TextColumn::make('language.title')
                    ->label('Language')
                    ->sortable(),
                TextColumn::make('seo_title')
                    ->limit(60)
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRouteSeos::route('/'),
            'create' => CreateRouteSeo::route('/create'),
            'view' => ViewRouteSeo::route('/{record}'),
            'edit' => EditRouteSeo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('seoable_type', 'route')
            ->where('seoable_id', 0);
    }
}
