<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Filament\Pages\VideoSystemHelp;
use App\Filament\Resources\Videos\VideoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVideo extends EditRecord
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
            DeleteAction::make(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return VideoResource::validateAndNormalize($data);
    }
}
