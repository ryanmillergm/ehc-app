<?php

namespace App\Filament\Resources\Videoables\Pages;

use App\Filament\Resources\Videoables\VideoableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideoables extends ListRecords
{
    protected static string $resource = VideoableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
