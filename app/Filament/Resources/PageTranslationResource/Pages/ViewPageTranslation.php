<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Resources\PageTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPageTranslation extends ViewRecord
{
    protected static string $resource = PageTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
