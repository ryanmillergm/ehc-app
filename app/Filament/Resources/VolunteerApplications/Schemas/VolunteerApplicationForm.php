<?php

namespace App\Filament\Resources\VolunteerApplications\Schemas;

use App\Models\VolunteerApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ViewField::make('answers_render')
                        ->label(false)
                        ->view('filament.volunteer-applications.answers')
                        ->dehydrated(false),
                ])
                ->columns(1),

            Section::make('Availability')
                ->schema([
                    // Sunday -> Saturday, AM/PM
                    self::dayRow('sun', 'Sunday'),
                    self::dayRow('mon', 'Monday'),
                    self::dayRow('tue', 'Tuesday'),
                    self::dayRow('wed', 'Wednesday'),
                    self::dayRow('thu', 'Thursday'),
                    self::dayRow('fri', 'Friday'),
                    self::dayRow('sat', 'Saturday'),
                ])
                ->columns(1)
                ->collapsed(),

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

                    TagsInput::make('interests')
                        ->label('Interests')
                        ->placeholder('Add interest...')
                        ->suggestions(['food', 'prayer', 'kids', 'tech'])
                        ->nullable(),

                    Textarea::make('internal_notes')
                        ->rows(8)
                        ->placeholder('Internal notes / follow-up...')
                        ->nullable(),
                ])
                ->columns(1),
        ]);
    }

    private static function dayRow(string $dayKey, string $label)
    {
        return \Filament\Schemas\Components\Grid::make(3)
            ->schema([
                TextInput::make("availability_labels.{$dayKey}")
                    ->label(false)
                    ->default($label)
                    ->disabled()
                    ->dehydrated(false),

                Toggle::make("availability.{$dayKey}.am")
                    ->label('AM')
                    ->default(false),

                Toggle::make("availability.{$dayKey}.pm")
                    ->label('PM')
                    ->default(false),
            ]);
    }
}
