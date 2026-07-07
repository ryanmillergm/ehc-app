<?php

namespace App\Filament\Resources\RouteSeoResource\Pages;

use App\Filament\Pages\SeoDocumentation;
use App\Filament\Resources\RouteSeoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRouteSeos extends ListRecords
{
    protected static string $resource = RouteSeoResource::class;

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
