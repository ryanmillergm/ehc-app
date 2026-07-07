<?php

namespace App\Filament\Resources\RouteSeoResource\Pages;

use App\Filament\Resources\RouteSeoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRouteSeo extends CreateRecord
{
    protected static string $resource = RouteSeoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['seoable_type'] = 'route';
        $data['seoable_id'] = 0;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
