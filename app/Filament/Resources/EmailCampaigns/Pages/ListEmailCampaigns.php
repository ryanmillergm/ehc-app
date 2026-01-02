<?php

namespace App\Filament\Resources\EmailCampaigns\Pages;

use App\Filament\Pages\EmailSystemHelp;
use App\Filament\Resources\EmailCampaigns\EmailCampaignResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailCampaigns extends ListRecords
{
    protected static string $resource = EmailCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('help')
                ->label('Help')
                ->icon('heroicon-o-question-mark-circle')
                ->url(EmailSystemHelp::getUrl())
                ->openUrlInNewTab(),

            CreateAction::make(),
        ];
    }
}
