<?php

namespace App\Filament\Resources\ImageGroupItems\Pages;

use App\Filament\Resources\ImageGroupItems\ImageGroupItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageGroupItems extends ListRecords
{
    protected static string $resource = ImageGroupItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
