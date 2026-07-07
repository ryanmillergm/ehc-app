<?php

namespace App\Filament\Resources\Videoables\Pages;

use App\Filament\Pages\VideoSystemHelp;
use App\Filament\Resources\Videoables\VideoableResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVideoable extends EditRecord
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
            DeleteAction::make(),
        ];
    }
}
