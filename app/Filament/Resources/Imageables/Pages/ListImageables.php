<?php

namespace App\Filament\Resources\Imageables\Pages;

use App\Filament\Resources\Imageables\ImageableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageables extends ListRecords
{
    protected static string $resource = ImageableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
