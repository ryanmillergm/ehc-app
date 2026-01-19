<?php

namespace App\Filament\Resources\ApplicationForms\Schemas;

use App\Models\ApplicationForm;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ApplicationFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basics')
                ->schema([
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
                ])
                ->columns(2),

            Section::make('Thank you message')
                ->schema([
                    Select::make('thank_you_format')
                        ->label('Format')
                        ->options([
                            ApplicationForm::THANK_YOU_TEXT    => 'Text (plain)',
                            ApplicationForm::THANK_YOU_WYSIWYG => 'Editor (WYSIWYG)',
                            ApplicationForm::THANK_YOU_HTML    => 'HTML (Advanced)',
                        ])
                        ->default(ApplicationForm::THANK_YOU_WYSIWYG)
                        ->required()
                        ->live(),

                    // Plain text UI field (virtual)
                    Textarea::make('thank_you_text')
                        ->label('Thank you message (Text)')
                        ->helperText('Shown after successful submission.')
                        ->rows(8)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_WYSIWYG) === ApplicationForm::THANK_YOU_TEXT)
                        ->dehydrated(true),

                    // WYSIWYG UI field (virtual)
                    RichEditor::make('thank_you_wysiwyg')
                        ->label('Thank you message (Editor)')
                        ->helperText('Easy editing. HTML is sanitized on save.')
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'link',
                            'bulletList',
                            'orderedList',
                            'blockquote',
                            'undo',
                            'redo',
                        ])
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('application-forms/thank-you')
                        ->visible(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_WYSIWYG) === ApplicationForm::THANK_YOU_WYSIWYG)
                        ->dehydrated(true),

                    // HTML UI field (virtual)
                    CodeEditor::make('thank_you_html')
                        ->label('Thank you message (HTML)')
                        ->helperText('Advanced: HTML is sanitized on save.')
                        ->language(Language::Html)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => ($get('thank_you_format') ?? ApplicationForm::THANK_YOU_WYSIWYG) === ApplicationForm::THANK_YOU_HTML)
                        ->dehydrated(true),
                ])
                ->columns(1),
        ]);
    }
}
