<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroup;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\PageTranslationResource\Pages\ListPageTranslations;
use App\Filament\Resources\PageTranslationResource\Pages\CreatePageTranslation;
use App\Filament\Resources\PageTranslationResource\Pages\ViewPageTranslation;
use App\Filament\Resources\PageTranslationResource\Pages\EditPageTranslation;
use App\Filament\Resources\PageTranslationResource\Pages;
use App\Filament\Resources\PageTranslationResource\RelationManagers;
use App\Models\PageTranslation;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PageTranslationResource extends Resource
{
    protected static ?string $model = PageTranslation::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Language;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Pages;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(PageTranslation::getForm());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('page_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('language.title')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPageTranslations::route('/'),
            'create' => CreatePageTranslation::route('/create'),
            'view' => ViewPageTranslation::route('/{record}'),
            'edit' => EditPageTranslation::route('/{record}/edit'),
        ];
    }
}
