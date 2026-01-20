<?php

namespace App\Filament\Resources\ApplicationForms\RelationManagers;

use App\Models\ApplicationForm;
use App\Models\FormField;
use Filament\Actions\Action;
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
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldPlacements';

    protected static ?string $title = 'Questions';
    protected static ?string $pluralModelLabel = 'Questions';
    protected static ?string $modelLabel = 'Question';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('selected_field_meta')
                ->dehydrated(false),

            /* ---------------------------------------------------------
             | Question selector (keeps the "+" inside the dropdown)
             * --------------------------------------------------------- */
            Select::make('form_field_id')
                ->label('Question')
                ->required()
                ->searchable()
                ->placeholder('Select or search for a question...')
                ->options(fn (): array => FormField::query()
                    ->orderBy('key')
                    ->limit(50)
                    ->get(['id', 'key', 'label'])
                    ->mapWithKeys(fn (FormField $f) => [
                        $f->id => "{$f->key} — {$f->label}",
                    ])
                    ->all()
                )
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
                        ->select(['key', 'label'])
                        ->find($value);

                    return $field
                        ? "{$field->key} — {$field->label}"
                        : null;
                })
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    if (! $state) {
                        $set('selected_field_meta', null);
                        return;
                    }

                    $field = FormField::query()
                        ->select(['key', 'type', 'label'])
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
                        return 'Pick an existing question, or create a new one.';
                    }

                    return sprintf(
                        'Selected: %s — %s (%s)',
                        $this->prettyFieldType($meta['type'] ?? ''),
                        $meta['label'] ?? '',
                        $meta['key'] ?? '',
                    );
                })
                //  Inline "+" create (power-user flow)
                ->createOptionForm($this->questionCreateForm())
                ->createOptionUsing(fn (array $data): int =>
                    (int) $this->createFormFieldFromModal($data)->getKey()
                )
                //  Non-deprecated uniqueness validation (scoped to this ApplicationForm)
                ->unique(
                    table: 'form_field_placements',
                    column: 'form_field_id',
                    ignorable: fn ($record) => $record, // ignore current placement on edit
                    modifyRuleUsing: function (Unique $rule): Unique {
                        /** @var ApplicationForm $owner */
                        $owner = $this->getOwnerRecord();

                        return $rule
                            ->where('fieldable_type', ApplicationForm::class)
                            ->where('fieldable_id', $owner->getKey());
                    },
                ),

            /* ---------------------------------------------------------
             | CTA BELOW the select (Filament v4 native)
             * --------------------------------------------------------- */
            SchemaActions::make([
                Action::make('createNewQuestionBelow')
                    ->label('+ Create new question')
                    ->color('primary')
                    ->link()
                    ->modalHeading('Create new question')
                    ->schema($this->questionCreateForm())
                    ->action(function (array $data, Set $set): void {
                        $field = $this->createFormFieldFromModal($data);

                        $set('form_field_id', (int) $field->getKey());

                        $set('selected_field_meta', [
                            'key'   => $field->key,
                            'type'  => $field->type,
                            'label' => $field->label,
                        ]);
                    }),
            ])
                ->columnSpanFull(),

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
                ->collapsed()
                ->schema([
                    TextInput::make('label_override')
                        ->label('Label override')
                        ->maxLength(160),

                    Textarea::make('help_text_override')
                        ->label('Help text override')
                        ->rows(2),

                    KeyValue::make('config_override')
                        ->label('Config override')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /* ---------------------------------------------------------
     | Shared create-question modal schema
     * --------------------------------------------------------- */
    private function questionCreateForm(): array
    {
        return [
            TextInput::make('key')
                ->label('Key')
                ->required()
                ->rule('alpha_dash')
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
                ->columnSpanFull(),
        ];
    }

    private function createFormFieldFromModal(array $data): FormField
    {
        return FormField::create([
            'key'       => $data['key'],
            'type'      => $data['type'],
            'label'     => $data['label'],
            'help_text' => $data['help_text'] ?? null,
            'config'    => $data['config'] ?? [],
        ]);
    }

    /* ---------------------------------------------------------
     | Table
     * --------------------------------------------------------- */
    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('sort')->sortable(),

                Tables\Columns\TextColumn::make('field.key')
                    ->label('Key')
                    ->searchable(),

                Tables\Columns\TextColumn::make('field.type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) =>
                        $this->prettyFieldType((string) $state)
                    ),

                Tables\Columns\IconColumn::make('is_required')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            default => ucfirst($type),
        };
    }
}
