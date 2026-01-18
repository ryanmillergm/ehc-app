<?php

namespace App\Filament\Resources\VolunteerNeeds\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;

class VolunteerNeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Volunteer Need')
                ->columns(12)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get) {
                            // Only auto-fill slug if it hasn't been set yet.
                            if (filled($get('slug'))) {
                                return;
                            }

                            $set('slug', Str::slug($state ?? ''));
                        })
                        ->columnSpan(7),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->columnSpan(5),

                    Textarea::make('description')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->default(true)
                        ->columnSpan(4),

                    TextInput::make('capacity')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->columnSpan(4),

                    // Leave out until we add events
                    // Select::make('event_id')
                    //     ->label('Event')
                    //     ->nullable()
                    //     ->searchable()
                    //     ->preload()
                    //     ->disabled()
                    //     ->helperText('Event linking can be enabled later.')
                    //     ->columnSpan(4),
                ]),
        ]);
    }
}
