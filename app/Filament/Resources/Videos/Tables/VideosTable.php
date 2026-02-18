<?php

namespace App\Filament\Resources\Videos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('source_type')->badge()->sortable(),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('embed_url')->limit(50)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('path')->limit(50)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration_seconds')->numeric()->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
