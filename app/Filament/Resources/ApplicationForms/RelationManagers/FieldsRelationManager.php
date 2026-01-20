<?php

namespace App\Filament\Resources\ApplicationForms\RelationManagers;

use App\Models\ApplicationForm;
use App\Models\FormField;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class FieldsRelationManager extends RelationManager
{
    /**
     * ApplicationForm::fieldPlacements()
     */
    protected static string $relationship = 'fieldPlacements';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Stores selected field meta so helperText can update immediately.
            Hidden::make('selected_field_meta')
                ->dehydrated(false)
                ->default(null),

            Select::make('form_field_id')
                ->label('Question')
                ->required()
                ->searchable() // manual searchable (no args)
                ->placeholder('Select or search for a question...')
                ->options(fn (): array => FormField::query()
                    ->orderBy('key')
                    ->limit(50)
                    ->get(['id', 'key', 'label'])
                    ->mapWithKeys(fn (FormField $f) => [
                        $f->id => "{$f->key} — {$f->label}",
                    ])
                    ->all())
                ->getSearchResultsUsing(function (string $search): array {
                    return FormField::query()
                        ->where('key', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%")
                        ->orderBy('key')
                        ->limit(50)
                        ->get(['id', 'key', 'label'])
                        ->mapWithKeys(fn (FormField $f) => [
                            $f->id => "{$f->key} — {$f->label}",
                        ])
                        ->all();
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    if (! $value) {
                        return null;
                    }

                    $field = FormField::query()
                        ->select(['id', 'key', 'label'])
                        ->find($value);

                    return $field ? "{$field->key} — {$field->label}" : null;
                })
                ->live()

                // Create new FormField from inside the select
                ->createOptionForm([
                    TextInput::make('key')
                        ->label('Key')
                        ->required()
                        ->rule('alpha_dash')
                        ->maxLength(64)
                        ->unique(FormField::class, 'key'),

                    Select::make('type')
                        ->label('Type')
                        ->required()
                        ->options([
                            'text'           => 'Text input',
                            'textarea'       => 'Textarea',
                            'select'         => 'Select',
                            'radio'          => 'Radio',
                            'checkbox_group' => 'Checkbox group',
                            'toggle'         => 'Toggle',
                        ])
                        ->default('text'),

                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(160),

                    Textarea::make('help_text')
                        ->label('Help text')
                        ->rows(2),

                    KeyValue::make('config')
                        ->label('Config')
                        ->helperText('Example keys: options, rows, min, max, placeholder.')
                        ->columnSpanFull(),
                ])
                ->createOptionUsing(function (array $data): int {
                    $field = FormField::create([
                        'key'       => $data['key'],
                        'type'      => $data['type'],
                        'label'     => $data['label'],
                        'help_text' => $data['help_text'] ?? null,

                        // normalize config storage
                        'config'    => in_array($data['type'], ['radio', 'select', 'checkbox_group'], true)
                            ? ['options' => ($data['config'] ?? [])]
                            : ($data['config'] ?? []),
                    ]);

                    return (int) $field->getKey();
                })

                // Update helper text meta when selection changes
                ->afterStateUpdated(function ($state, Set $set): void {
                    if (! $state) {
                        $set('selected_field_meta', null);
                        return;
                    }

                    $field = FormField::query()
                        ->select(['id', 'key', 'type', 'label'])
                        ->find($state);

                    if (! $field) {
                        $set('selected_field_meta', null);
                        return;
                    }

                    $set('selected_field_meta', [
                        'key'   => $field->key,
                        'type'  => $field->type,
                        'label' => $field->label,
                    ]);
                })

                ->helperText(function (Get $get): string {
                    $meta = $get('selected_field_meta');

                    if (! is_array($meta)) {
                        return 'Pick an existing question from your global library, or create a new one.';
                    }

                    $type  = (string) ($meta['type'] ?? '');
                    $label = (string) ($meta['label'] ?? '');
                    $key   = (string) ($meta['key'] ?? '');

                    $typePretty = $this->prettyFieldType($type);
                    $main = trim(implode(' — ', array_filter([$typePretty, $label])));

                    return trim("Selected: {$main} ({$key})");
                })

                // Ensure a field can’t be placed twice on the same form.
                ->rules(function () {
                    /** @var ApplicationForm $owner */
                    $owner = $this->getOwnerRecord();

                    return [
                        Rule::unique('form_field_placements', 'form_field_id')
                            ->where('fieldable_type', ApplicationForm::class)
                            ->where('fieldable_id', $owner->getKey())
                            ->ignore($this->getMountedTableActionRecord()?->getKey()),
                    ];
                }),

            Toggle::make('is_required')
                ->label('Required')
                ->default(false),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),

            TextInput::make('sort')
                ->numeric()
                ->default(100)
                ->helperText('Lower numbers appear first.'),

            Section::make('Advanced (Overrides)')
                ->description('Only use these if this form needs different wording or config than the global question.')
                ->collapsed()
                ->schema([
                    TextInput::make('label_override')
                        ->label('Label override')
                        ->maxLength(160)
                        ->helperText('Overrides the label for this form only.'),

                    Textarea::make('help_text_override')
                        ->label('Help text override')
                        ->rows(2)
                        ->helperText('Overrides help text for this form only.'),

                    KeyValue::make('config_override')
                        ->label('Config override')
                        ->helperText('Merged on top of the base field config. Example keys: options, rows, min, max, placeholder.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('field.key')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('sort')->sortable(),

                Tables\Columns\TextColumn::make('field.key')
                    ->label('Key')
                    ->searchable(),

                Tables\Columns\TextColumn::make('field.type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $this->prettyFieldType((string) $state)),

                Tables\Columns\TextColumn::make('label_override')
                    ->label('Label')
                    ->state(fn ($record) => $record->label())
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_required')->boolean()->label('Req'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()->label('Add question'),
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

    private function prettyFieldType(string $type): string
    {
        return match ($type) {
            'text' => 'Text input',
            'textarea' => 'Textarea',
            'select' => 'Select',
            'radio' => 'Radio',
            'checkbox_group' => 'Checkbox group',
            'toggle' => 'Toggle',
            default => $type,
        };
    }
}
