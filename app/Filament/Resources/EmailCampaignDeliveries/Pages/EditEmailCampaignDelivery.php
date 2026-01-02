<?php

namespace App\Filament\Resources\EmailCampaignDeliveries\Pages;

use App\Filament\Resources\EmailCampaignDeliveries\EmailCampaignDeliveryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailCampaignDelivery extends EditRecord
{
    protected static string $resource = EmailCampaignDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
