<?php

namespace App\Filament\Resources\VolunteerApplications\Pages;

use App\Filament\Resources\VolunteerApplications\VolunteerApplicationResource;
use App\Models\VolunteerApplication;
use Filament\Resources\Pages\Page;

class PrintVolunteerApplication extends Page
{
    protected static string $resource = VolunteerApplicationResource::class;

    protected string $view = 'filament.resources.volunteer-applications.pages.print';

    public VolunteerApplication $record;

    public function mount(VolunteerApplication $record): void
    {
        $this->record = $record->loadMissing([
            'user',
            'need.applicationForm' => fn ($q) => $q->with([
                //  filter on placements (form_field_placements.is_active)
                'fieldPlacements' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort')
                    ->with('field'),
            ]),
        ]);
    }

    public function getTitle(): string
    {
        return 'Print Volunteer Application';
    }

    public function hasLogo(): bool { return false; }
    public function hasTopbar(): bool { return false; }
    public function hasSidebar(): bool { return false; }
}
