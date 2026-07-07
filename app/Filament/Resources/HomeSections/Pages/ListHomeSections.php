<?php

namespace App\Filament\Resources\HomeSections\Pages;

use App\Filament\Pages\HomeSectionsDocumentation;
use App\Filament\Resources\HomeSections\HomeSectionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeSections extends ListRecords
{
    protected static string $resource = HomeSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('home_sections_docs')
                ->label('Home Sections Docs')
                ->icon('heroicon-o-book-open')
                ->url(HomeSectionsDocumentation::getUrl())
                ->openUrlInNewTab(),
            CreateAction::make(),
        ];
    }
}
