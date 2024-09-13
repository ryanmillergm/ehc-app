<?php

namespace App\Filament\Org\Resources\ChildResource\Pages;

use App\Filament\Org\Resources\ChildResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChildren extends ListRecords
{
    protected static string $resource = ChildResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
