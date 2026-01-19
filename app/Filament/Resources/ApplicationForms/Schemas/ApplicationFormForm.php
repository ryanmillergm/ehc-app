<?php

namespace App\Filament\Resources\ApplicationForms\Schemas;

use App\Models\ApplicationForm;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class ApplicationFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
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
                    ->default(true)
                    ->columnSpanFull(),
            ]),

            Group::make()->schema([
                Select::make('thank_you_format')
                    ->label('Thank you message format')
                    ->options([
                        ApplicationForm::THANK_YOU_TEXT => 'Text (plain)',
                        ApplicationForm::THANK_YOU_HTML  => 'HTML (Code)',
                    ])
                    ->default(ApplicationForm::THANK_YOU_TEXT)
                    ->required()
                    ->live(),

                // Plain text
                Textarea::make('thank_you_content')
                    ->label('Thank you message')
                    ->helperText('Shown after successful submission (plain text).')
                    ->rows(8)
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_TEXT) === ApplicationForm::THANK_YOU_TEXT)
                    ->dehydrated(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_TEXT) === ApplicationForm::THANK_YOU_TEXT),

                // HTML code editor
                CodeEditor::make('thank_you_content')
                    ->label('Thank you message (HTML)')
                    ->helperText('HTML will be sanitized for safety (scripts/events/javascript: removed).')
                    ->language(Language::Html)
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_TEXT) === ApplicationForm::THANK_YOU_HTML)
                    ->dehydrated(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_TEXT) === ApplicationForm::THANK_YOU_HTML),

                // Sanitized preview
                \Filament\Forms\Components\Placeholder::make('thank_you_preview')
                    ->label('Preview (sanitized)')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_TEXT) === ApplicationForm::THANK_YOU_HTML)
                    ->content(function (Get $get): string {
                        $dirty = (string) ($get('thank_you_content') ?? '');

                        try {
                            $clean = Purify::config('application_form_thank_you')->clean($dirty);
                        } catch (\Throwable $e) {
                            $clean = Purify::clean($dirty);
                        }

                        return '<div class="prose max-w-none">' . $clean . '</div>';
                    })
                    ->html(),
            ])->columnSpanFull(),
        ]);
    }
}
