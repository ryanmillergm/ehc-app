<?php

namespace App\Filament\Resources\ImageGroupables\Pages;

use App\Filament\Resources\ImageGroupables\ImageGroupableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageGroupables extends ListRecords
{
    protected static string $resource = ImageGroupableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
