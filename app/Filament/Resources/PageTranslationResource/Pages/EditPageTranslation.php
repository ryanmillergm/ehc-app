<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use App\Filament\Resources\PageTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPageTranslation extends EditRecord
{
    protected static string $resource = PageTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
