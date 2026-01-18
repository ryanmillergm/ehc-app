<?php

namespace App\Filament\Resources\VolunteerApplications\Schemas;

use App\Models\VolunteerApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VolunteerApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')
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

                    Select::make('status')
                        ->required()
                        ->options([
                            VolunteerApplication::STATUS_SUBMITTED => 'Submitted',
                            VolunteerApplication::STATUS_REVIEWING => 'Reviewing',
                            VolunteerApplication::STATUS_ACCEPTED  => 'Accepted',
                            VolunteerApplication::STATUS_REJECTED  => 'Rejected',
                            VolunteerApplication::STATUS_WITHDRAWN => 'Withdrawn',
                        ]),

                    Textarea::make('message')
                        ->label('Applicant message')
                        ->rows(6)
                        ->disabled()
                        ->dehydrated(false),

                    TagsInput::make('interests')
                        ->label('Interests')
                        ->placeholder('food, cleanup, prayer, ...')
                        ->helperText('What areas they selected on the form.')
                        ->nullable(),

                    TagsInput::make('availability')
                        ->label('Availability')
                        ->placeholder('thursday, sunday, flexible, ...')
                        ->nullable(),

                    Textarea::make('internal_notes')
                        ->rows(6)
                        ->placeholder('Internal notes / follow-up...')
                        ->nullable(),
                ])
                ->columns(1),
        ]);
    }
}
