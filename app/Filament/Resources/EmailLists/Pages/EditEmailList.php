<?php

namespace App\Filament\Resources\EmailLists\Pages;

use App\Filament\Resources\EmailLists\EmailListResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailList extends EditRecord
{
    protected static string $resource = EmailListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
