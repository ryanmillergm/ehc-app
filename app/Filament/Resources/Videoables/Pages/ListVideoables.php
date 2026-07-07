<?php

namespace App\Filament\Resources\Videoables\Pages;

use App\Filament\Pages\VideoSystemHelp;
use App\Filament\Resources\Videoables\VideoableResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideoables extends ListRecords
{
    protected static string $resource = VideoableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('help')
                ->label('Help')
                ->icon('heroicon-o-question-mark-circle')
                ->url(VideoSystemHelp::getUrl())
                ->openUrlInNewTab(),
            CreateAction::make(),
        ];
    }
}
