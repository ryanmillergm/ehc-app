<?php

namespace App\Filament\Resources\ApplicationForms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ApplicationFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    if (! $get('slug') && filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('slug')
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),

            Toggle::make('is_active')->default(true),

            Toggle::make('use_availability')
                ->label('Include availability block (Mon–Sun AM/PM)')
                ->helperText('If enabled, applicants will see the built-in weekly availability grid.')
                ->default(true),

            // Thank you message settings
            Select::make('thank_you_format')
                ->label('Thank you message format')
                ->options([
                    'text' => 'Text',
                    'html' => 'HTML',
                ])
                ->default('text')
                ->required()
                ->live(),

            Textarea::make('thank_you_content')
                ->label('Thank you message content')
                ->helperText('Shown after successful submission. If format is HTML, this will be rendered as HTML.')
                ->rows(6)
                ->columnSpanFull(),
        ]);
    }
}
