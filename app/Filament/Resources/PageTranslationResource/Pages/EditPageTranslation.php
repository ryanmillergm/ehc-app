<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Pages\PageAuthoringHelp;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\PageTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageTranslation extends EditRecord
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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Page Translation updated';
    }
}
