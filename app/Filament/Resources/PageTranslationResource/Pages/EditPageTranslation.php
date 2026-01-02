<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

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
