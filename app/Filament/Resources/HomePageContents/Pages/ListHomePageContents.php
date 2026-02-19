<?php

namespace App\Filament\Resources\HomePageContents\Pages;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\HomePageContents\HomePageContentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomePageContents extends ListRecords
{
    protected static string $resource = HomePageContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seo_docs')
                ->label('SEO Docs')
                ->icon('heroicon-o-book-open')
                ->url(SeoDocumentation::getUrl())
                ->openUrlInNewTab(),
            CreateAction::make(),
        ];
    }
}
