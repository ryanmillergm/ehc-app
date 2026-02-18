<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Filament\Pages\VideoSystemHelp;
use App\Filament\Resources\Videos\VideoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVideos extends ListRecords
{
    protected static string $resource = VideoResource::class;

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
