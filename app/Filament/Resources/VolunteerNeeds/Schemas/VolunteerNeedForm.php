<?php

namespace App\Filament\Resources\VolunteerNeeds\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class VolunteerNeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(160)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    if (! $get('slug') && filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('slug')
                ->required()
                ->maxLength(160)
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->rows(4)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->default(true),

            TextInput::make('capacity')
                ->numeric()
                ->minValue(1)
                ->nullable(),

            TextInput::make('event_id')
                ->numeric()
                ->nullable()
                ->helperText('Optional. If you later link this to an Event model, we’ll switch this to a relationship select.'),

            // link to form builder
            Select::make('application_form_id')
                ->label('Application Form')
                ->relationship('applicationForm', 'name')
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Controls which questions appear on the public application page.'),
        ]);
    }
}
