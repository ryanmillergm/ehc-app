<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Pages\PageAuthoringHelp;
use App\Filament\Resources\PageTranslationResource;
use Filament\Actions\Action;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePageTranslation extends CreateRecord
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
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Page Translation created';
    }
}
