<?php

namespace App\Filament\Org\Resources\ChildResource\Pages;

use App\Filament\Org\Resources\ChildResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateChild extends CreateRecord
{
    protected static string $resource = ChildResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Child created';
    }
}
