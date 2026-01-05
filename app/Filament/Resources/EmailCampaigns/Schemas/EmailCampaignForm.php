<?php

namespace App\Filament\Resources\EmailCampaigns\Schemas;

use App\Filament\Forms\Components\GrapesEmailBuilder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EmailCampaignForm
{
    /**
     * Keep DOM mounted; hide by moving off-screen.
     * (This avoids ProseMirror teardown weirdness.)
     */
    protected static function offscreenStyle(string $mode): string
    {
        // NOTE: avoid height:0 here; tiny size tends to be friendlier to RichEditor internals
        return "editor === '{$mode}' ? '' : 'position:absolute;left:-10000px;top:0;width:1px;height:1px;overflow:hidden;pointer-events:none;opacity:0;'";
    }

    protected static function xData(): string
    {
        return "{ editor: \$wire.entangle('data.editor') }";
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            
                Section::make('Campaign')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->components([
                        Select::make('email_list_id')
                            ->label('Email List')
                            ->relationship(name: 'emailList', titleAttribute: 'label')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255),

                        Select::make('editor')
                            ->label('Editor')
                            ->options([
                                'grapesjs' => 'Designer (GrapesJS)',
                                'rich'     => 'WYSIWYG (Rich Editor)',
                                'html'     => 'Raw HTML (paste full document)',
                            ])
                            ->default('grapesjs')
                            ->live()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                // --- GrapesJS ---
                Section::make('Email Designer')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->extraAttributes([
                        'x-data' => self::xData(),
                        'x-bind:style' => self::offscreenStyle('grapesjs'),
                    ])
                    ->components([
                        GrapesEmailBuilder::make('designer')
                            ->dehydrated(false),

                        Hidden::make('design_html'),
                        Hidden::make('design_css'),
                        Hidden::make('design_json'),
                    ]),

                // --- RichEditor ---
                Section::make('WYSIWYG Editor')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->extraAttributes([
                        'x-data' => self::xData(),
                        'x-bind:style' => self::offscreenStyle('rich'),
                    ])
                    ->components([
                        RichEditor::make('body_html')
                            ->label('Body')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3',
                                'bulletList', 'orderedList',
                                'blockquote',
                                'link',
                                'undo', 'redo',
                            ])
                            ->required(fn (Get $get) => ($get('editor') ?? 'grapesjs') === 'rich')
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (($get('editor') ?? null) === 'rich') {
                                    $set('body_html_source', (string) $state);
                                }
                            }),
                    ]),

                // --- Raw HTML ---
                Section::make('Raw HTML')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->extraAttributes([
                        'x-data' => self::xData(),
                        'x-bind:style' => self::offscreenStyle('html'),
                    ])
                    ->components([
                        Textarea::make('body_html_source')
                            ->label('Body HTML (paste full document OK)')
                            ->rows(20)
                            ->columnSpanFull()
                            ->helperText('Paste full HTML if you want. On save, we will extract <body> and inline any <style> blocks we can find.')
                            ->extraInputAttributes(['class' => 'font-mono text-xs'])
                            ->formatStateUsing(fn ($state, $record) => filled($state) ? $state : ($record?->body_html ?? ''))
                            ->required(fn (Get $get) => ($get('editor') ?? 'grapesjs') === 'html')
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (($get('editor') ?? null) === 'html') {
                                    $set('body_html', (string) $state);
                                }
                            }),
                    ]),
        ]);
    }
}
