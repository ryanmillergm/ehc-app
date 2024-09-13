<?php

namespace App\Filament\App\Resources\ChildResource\Pages;

use App\Filament\App\Resources\ChildResource;
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