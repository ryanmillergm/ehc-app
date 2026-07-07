<?php

namespace App\Filament\Resources\RouteSeoResource\Pages;

use App\Filament\Resources\RouteSeoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRouteSeo extends EditRecord
{
    protected static string $resource = RouteSeoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['seoable_type'] = 'route';
        $data['seoable_id'] = 0;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
