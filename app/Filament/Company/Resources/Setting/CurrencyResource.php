<?php

namespace App\Filament\Company\Resources\Setting;

use App\Filament\Company\Resources\Setting\CurrencyResource\Pages;
use App\Models\Banking\Account;
use App\Models\Setting\Currency;
use App\Services\CurrencyService;
use App\Traits\ChecksForeignKeyConstraints;
use Closure;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\{Forms, Tables};
use Illuminate\Database\Eloquent\Collection;
use Wallo\FilamentSelectify\Components\ToggleButton;

class CurrencyResource extends Resource
{
    use ChecksForeignKeyConstraints;

    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $slug = 'settings/currencies';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\Select::make('code')
                            ->label('Code')
                            ->options(Currency::getAvailableCurrencyCodes())
                            ->searchable()
                            ->placeholder('Select a currency code...')
                            ->live()
                            ->required()
                            ->hidden(static fn (Forms\Get $get, $state): bool => $get('enabled') && $state !== null)
                            ->afterStateUpdated(static function (Forms\Set $set, $state) {
                                $fields = ['name', 'rate', 'precision', 'symbol', 'symbol_first', 'decimal_mark', 'thousands_separator'];

                                if ($state === null) {
                                    foreach ($fields as $field) {
                                        $set($field, null);
                                    }
                                    return;
                                }

                                $defaultCurrencyCode = Currency::getDefaultCurrencyCode();
                                $currencyService = app(CurrencyService::class);

                                $code = $state;
                                $allCurrencies = Currency::getAllCurrencies();
                                $selectedCurrencyCode = $allCurrencies[$code] ?? [];

                                $rate = $defaultCurrencyCode ? $currencyService->getCachedExchangeRate($defaultCurrencyCode, $code) : 1;

                                foreach ($fields as $field) {
                                    $set($field, $selectedCurrencyCode[$field] ?? ($field === 'rate' ? $rate : ''));
                                }
                            }),
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->hidden(static fn (Forms\Get $get): bool => ! ($get('enabled') && $get('code') !== null))
                            ->disabled(static fn (Forms\Get $get): bool => $get('enabled'))
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->maxLength(50)
                            ->required(),
                        Forms\Components\TextInput::make('rate')
                            ->label('Rate')
                            ->numeric()
                            ->rule('gt:0')
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('precision')
                            ->label('Precision')
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->placeholder('Select a precision...')
                            ->options(['0', '1', '2', '3', '4'])
                            ->required(),
                        Forms\Components\TextInput::make('symbol')
                            ->label('Symbol')
                            ->maxLength(5)
                            ->required(),
                        Forms\Components\Select::make('symbol_first')
                            ->label('Symbol Position')
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->formatStateUsing(static fn ($state) => isset($state) ? (int) $state : null)
                            ->boolean('Before Amount', 'After Amount', 'Select a symbol position...')
                            ->required(),
                        Forms\Components\TextInput::make('decimal_mark')
                            ->label('Decimal Separator')
                            ->maxLength(1)
                            ->required(),
                        Forms\Components\TextInput::make('thousands_separator')
                            ->label('Thousands Separator')
                            ->maxLength(1)
                            ->rule(static function (Forms\Get $get): Closure {
                                return static function ($attribute, $value, Closure $fail) use ($get) {
                                    $decimalMark = $get('decimal_mark');

                                    if ($value === $decimalMark) {
                                        $fail('The thousands separator and decimal separator must be different.');
                                    }
                                };
                            })
                            ->nullable(),
                        ToggleButton::make('enabled')
                            ->label('Default Currency')
                            ->live()
                            ->offColor('danger')
                            ->onColor('primary')
                            ->afterStateUpdated(static function (Forms\Set $set, Forms\Get $get, $state) {
                                $enabledState = (bool) $state;
                                $code = $get('code');

                                $defaultCurrencyCode = Currency::getDefaultCurrencyCode();
                                $currencyService = app(CurrencyService::class);

                                if ($enabledState) {
                                    $set('rate', 1);
                                } else {
                                    if ($code === null) {
                                        return;
                                    }

                                    $rate = $currencyService->getCachedExchangeRate($defaultCurrencyCode, $code);

                                    $set('rate', $rate ?? '');
                                }
                            }),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->weight('semibold')
                    ->icon(static fn (Currency $record) => $record->enabled ? 'heroicon-o-lock-closed' : null)
                    ->tooltip(static fn (Currency $record) => $record->enabled ? 'Default Currency' : null)
                    ->iconPosition('after')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Symbol')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate')
                    ->label('Rate')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(static function (Tables\Actions\DeleteAction $action, Currency $record) {
                        $defaultCurrency = $record->enabled;
                        $modelsToCheck = [
                            Account::class,
                        ];

                        $isUsed = self::isForeignKeyUsed('currency_code', $record->code, $modelsToCheck);

                        if ($defaultCurrency) {
                            Notification::make()
                                ->danger()
                                ->title('Action Denied')
                                ->body(__('The :name currency is currently set as the default currency and cannot be deleted. Please set a different currency as your default before attempting to delete this one.', ['name' => $record->name]))
                                ->persistent()
                                ->send();

                            $action->cancel();
                        } elseif ($isUsed) {
                            Notification::make()
                                ->danger()
                                ->title('Action Denied')
                                ->body(__('The :name currency is currently in use by one or more accounts and cannot be deleted. Please remove this currency from all accounts before attempting to delete it.', ['name' => $record->name]))
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(static function (Tables\Actions\DeleteBulkAction $action, Collection $records) {
                            foreach ($records as $record) {
                                $defaultCurrency = $record->enabled;
                                $modelsToCheck = [
                                    Account::class,
                                ];

                                $isUsed = self::isForeignKeyUsed('currency_code', $record->code, $modelsToCheck);

                                if ($defaultCurrency) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Action Denied')
                                        ->body(__('The :name currency is currently set as the default currency and cannot be deleted. Please set a different currency as your default before attempting to delete this one.', ['name' => $record->name]))
                                        ->persistent()
                                        ->send();

                                    $action->cancel();
                                } elseif ($isUsed) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Action Denied')
                                        ->body(__('The :name currency is currently in use by one or more accounts and cannot be deleted. Please remove this currency from all accounts before attempting to delete it.', ['name' => $record->name]))
                                        ->persistent()
                                        ->send();

                                    $action->cancel();
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
