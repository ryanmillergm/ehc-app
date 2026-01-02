<?php

namespace App\Filament\Resources\EmailCampaigns\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class EmailCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
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

            RichEditor::make('body_html')
                ->label('Body')
                ->columnSpanFull()
                ->required(),

            TextInput::make('status')
                ->disabled()
                ->dehydrated(false),

            TextInput::make('sent_count')
                ->numeric()
                ->disabled()
                ->dehydrated(false),
        ]);
    }
}
