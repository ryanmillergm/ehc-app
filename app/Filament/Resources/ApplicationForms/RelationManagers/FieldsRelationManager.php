<?php

namespace App\Filament\Resources\ApplicationForms\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->required()
                ->options([
                    'text'           => 'Text input',
                    'textarea'       => 'Textarea',
                    'select'         => 'Select',
                    'radio'          => 'Radio',
                    'checkbox_group' => 'Checkbox group',
                    'toggle'         => 'Toggle',
                ])
                ->live(),

            TextInput::make('key')
                ->required()
                ->maxLength(80)
                ->helperText('Stored in answers JSON. Example: "interests" or "security_experience".')
                ->regex('/^[a-z][a-z0-9_]*$/')
                ->unique(
                    table: 'application_form_fields',
                    column: 'key',
                    ignoreRecord: true,
                    modifyRuleUsing: function (Unique $rule): Unique {
                        /** @var \App\Models\ApplicationForm $owner */
                        $owner = $this->getOwnerRecord();

                        return $rule->where('application_form_id', $owner->getKey());
                    },
                ),

            TextInput::make('label')
                ->required()
                ->maxLength(160),

            Textarea::make('help_text')
                ->rows(2)
                ->columnSpanFull(),

            Toggle::make('is_required')->default(false),
            Toggle::make('is_active')->default(true),

            TextInput::make('sort')
                ->numeric()
                ->default(100)
                ->helperText('Lower shows first.'),

            // ----- Config fields -----

            KeyValue::make('config.options')
                ->label('Options')
                ->helperText('Key = stored value, Value = label shown to user.')
                ->visible(fn ($get) => in_array($get('type'), ['select', 'radio', 'checkbox_group'], true))
                ->columnSpanFull(),

            TextInput::make('config.min')
                ->numeric()
                ->visible(fn ($get) => in_array($get('type'), ['text', 'textarea'], true)),

            TextInput::make('config.max')
                ->numeric()
                ->visible(fn ($get) => in_array($get('type'), ['text', 'textarea'], true)),

            TextInput::make('config.rows')
                ->numeric()
                ->default(5)
                ->visible(fn ($get) => $get('type') === 'textarea'),

            TextInput::make('config.placeholder')
                ->visible(fn ($get) => in_array($get('type'), ['text', 'textarea'], true))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('sort')->sortable(),
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('key')->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\IconColumn::make('is_required')->boolean()->label('Req'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
