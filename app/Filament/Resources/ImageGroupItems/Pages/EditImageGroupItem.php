<?php

namespace App\Filament\Resources\ImageGroupItems\Pages;

use App\Filament\Resources\ImageGroupItems\ImageGroupItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageGroupItem extends EditRecord
{
    protected static string $resource = ImageGroupItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
