<?php

namespace App\Filament\Resources\VolunteerApplications\Schemas;

use App\Models\VolunteerApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VolunteerApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Applicant')
                ->schema([
                    TextInput::make('user.full_name')
                        ->label('Volunteer')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('user.email')
                        ->label('Email')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('need.title')
                        ->label('Need')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('need.slug')
                        ->label('Need slug')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(1),

            Section::make('Responses')
                ->schema([
                    // Renders builder-driven Q/A from:
                    // - $record->need->applicationForm->fields
                    // - $record->answers
                    ViewField::make('answers_render')
                        ->label(false)
                        ->view('filament.volunteer-applications.answers')
                        ->dehydrated(false),
                ])
                ->columns(1),

            Section::make('Review')
                ->schema([
                    Select::make('status')
                        ->required()
                        ->options([
                            VolunteerApplication::STATUS_SUBMITTED => 'Submitted',
                            VolunteerApplication::STATUS_REVIEWING => 'Reviewing',
                            VolunteerApplication::STATUS_ACCEPTED  => 'Accepted',
                            VolunteerApplication::STATUS_REJECTED  => 'Rejected',
                            VolunteerApplication::STATUS_WITHDRAWN => 'Withdrawn',
                        ]),

                    Textarea::make('internal_notes')
                        ->rows(8)
                        ->placeholder('Internal notes / follow-up...')
                        ->nullable(),
                ])
                ->columns(1),
        ]);
    }
}
