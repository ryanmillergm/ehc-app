<?php

namespace App\Filament\Resources\EmailCampaigns\Schemas;

use App\Filament\Forms\Components\GrapesEmailBuilder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class EmailCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 12,
            ])->schema([
                Section::make('Campaign')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->columns(2)
                    ->components([
                        Select::make('email_list_id')
                            ->label('Email List')
                            ->relationship(
                                name: 'emailList',
                                titleAttribute: 'label',
                                modifyQueryUsing: function (Builder $query, mixed $state): Builder {
                                    return $query->where(function (Builder $q) use ($state) {
                                        $q->where('purpose', 'marketing');

                                        if (filled($state)) {
                                            $q->orWhere('id', $state);
                                        }
                                    });
                                },
                            )
                            ->rules([
                                Rule::exists('email_lists', 'id')->where('purpose', 'marketing'),
                            ])
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255),

                        Select::make('editor')
                            ->options([
                                'grapesjs' => 'Designer (GrapesJS)',
                                'html'     => 'HTML',
                            ])
                            ->default('grapesjs')
                            ->live()
                            ->required(),
                    ]),

                Section::make('Email Designer')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->visible(fn (Get $get) => $get('editor') === 'grapesjs')
                    ->components([
                        GrapesEmailBuilder::make('design_json')
                            ->label('') // cleaner
                            ->columnSpanFull(),

                        Hidden::make('design_html'),
                        Hidden::make('design_css'),
                    ]),

                Section::make('HTML')
                    ->columnSpan(['default' => 1, 'lg' => 12])
                    ->visible(fn (Get $get) => $get('editor') === 'html')
                    ->components([
                        RichEditor::make('body_html')
                            ->label('Body')
                            ->columnSpanFull()
                            ->required(fn (Get $get) => $get('editor') === 'html'),
                    ]),

                Section::make('Status')
                    ->columnSpan(12)
                    ->collapsed()
                    ->columns(2)
                    ->components([
                        TextInput::make('status')->disabled()->dehydrated(false),
                        TextInput::make('sent_count')->numeric()->disabled()->dehydrated(false),
                    ]),
            ]),
        ]);
    }
}
