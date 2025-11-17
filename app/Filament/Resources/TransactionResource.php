<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Donations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount_cents')
                    ->label('Amount (cents)')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->default('usd'),
                Forms\Components\TextInput::make('type'),
                Forms\Components\TextInput::make('status'),
                Forms\Components\TextInput::make('payment_intent_id')->disabled(),
                Forms\Components\TextInput::make('charge_id')->disabled(),
                Forms\Components\TextInput::make('customer_id')->disabled(),
                Forms\Components\DateTimePicker::make('paid_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('payer_name')
                    ->label('Donor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_cents')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'succeeded' => 'Succeeded',
                        'failed'    => 'Failed',
                        'refunded'  => 'Refunded',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Transaction $record) =>
                        $record->status === 'succeeded' && $record->charge_id
                    )
                    ->action(function (Transaction $record, array $data): void {
                        /** @var StripeService $stripe */
                        $stripe = app(StripeService::class);

                        $amountCents = $data['amount_cents'] ?? null;
                        $stripe->refund($record, $amountCents ? (int) $amountCents : null);
                    })
                    ->form([
                        Forms\Components\TextInput::make('amount_cents')
                            ->label('Amount (cents; leave blank for full)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (Transaction $record) => $record->amount_cents),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'     => Pages\ListTransactions::route('/'),
            'create'    => Pages\CreateTransaction::route('/create'),
            'edit'      => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
