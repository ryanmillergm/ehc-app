<?php

namespace App\Filament\Resources\PageTranslationResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\PageTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPageTranslations extends ListRecords
{
    protected static string $resource = PageTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
