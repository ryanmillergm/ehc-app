<?php

namespace App\Filament\Resources\LanguageResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\LanguageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLanguage extends ViewRecord
{
    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
