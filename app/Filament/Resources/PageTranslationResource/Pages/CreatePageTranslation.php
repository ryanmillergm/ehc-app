<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Resources\PageTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePageTranslation extends CreateRecord
{
    protected static string $resource = PageTranslationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Page Translation created';
    }
}
