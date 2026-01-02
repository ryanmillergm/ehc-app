<?php

namespace App\Filament\Resources\EmailCampaignDeliveries\Pages;

use App\Filament\Resources\EmailCampaignDeliveries\EmailCampaignDeliveryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailCampaignDeliveries extends ListRecords
{
    protected static string $resource = EmailCampaignDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
