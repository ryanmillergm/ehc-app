<?php

namespace App\Filament\Resources\EmailLists\Pages;

use App\Filament\Resources\EmailLists\EmailListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailLists extends ListRecords
{
    protected static string $resource = EmailListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
