<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Pages\SeoDocumentation;
use Filament\Actions\CreateAction;
use App\Filament\Resources\PageTranslationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListPageTranslations extends ListRecords
{
    protected static string $resource = PageTranslationResource::class;

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
