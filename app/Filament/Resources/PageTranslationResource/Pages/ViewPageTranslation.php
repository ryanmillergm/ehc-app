<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Pages\PageAuthoringHelp;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use App\Filament\Resources\PageTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPageTranslation extends ViewRecord
{
    protected static string $resource = PageTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('page_docs')
                ->label('Page Docs')
                ->icon('heroicon-o-book-open')
                ->url(PageAuthoringHelp::getUrl())
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
