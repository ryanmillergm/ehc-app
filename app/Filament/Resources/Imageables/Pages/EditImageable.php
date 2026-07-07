<?php

namespace App\Filament\Resources\Imageables\Pages;

use App\Filament\Resources\Imageables\ImageableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageable extends EditRecord
{
    protected static string $resource = ImageableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
