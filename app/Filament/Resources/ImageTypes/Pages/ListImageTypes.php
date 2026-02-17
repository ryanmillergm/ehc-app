<?php

namespace App\Filament\Resources\ImageTypes\Pages;

use App\Filament\Resources\ImageTypes\ImageTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageTypes extends ListRecords
{
    protected static string $resource = ImageTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
