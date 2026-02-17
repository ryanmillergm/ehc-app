<?php

namespace App\Filament\Resources\ImageGroups\Pages;

use App\Filament\Resources\ImageGroups\ImageGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageGroups extends ListRecords
{
    protected static string $resource = ImageGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
