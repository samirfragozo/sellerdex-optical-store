<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Actions\RegisterSaleReturn;
use App\Enums\SaleDocumentType;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/** Admin-only post-sale actions: return items, adjust the value, void (or cancel a plan separe). */
class SaleReturnActions
{
    /** @return list<Action> */
    public static function make(): array
    {
        return [self::returnItems(), self::valueAdjustment(), self::voidSale()];
    }

    public static function returnItems(): Action
    {
        return Action::make('returnItems')
            ->label(__('app.sale_return.actions.return'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Sale $record): bool => self::canAct($record) && self::returnableLines($record) !== [])
            ->fillForm(fn (Sale $record): array => ['lines' => self::returnableLines($record)])
            ->schema(fn (Sale $record): array => [
                Repeater::make('lines')
                    ->label(__('app.fields.items'))
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->live()
                    ->schema([
                        Hidden::make('sale_item_id'),
                        Hidden::make('returnable'),
                        Hidden::make('restockable'),
                        TextInput::make('description')
                            ->label(__('app.fields.description'))
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('quantity')
                            ->label(__('app.fields.quantity'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(fn (Get $get): ?int => $get('returnable') === null ? null : (int) $get('returnable'))
                            ->default(0)
                            ->required(),
                        Toggle::make('restock')
                            ->label(__('app.sale_return.fields.restock'))
                            ->visible(fn (Get $get): bool => (bool) $get('restockable')),
                    ])
                    ->columns(3),
                Textarea::make('reason')
                    ->label(__('app.sale_return.fields.reason'))
                    ->required()
                    ->maxLength(255),
                ...self::moneyFields($record, fn (Get $get): int => self::maxMoneyForLines($record, (array) $get('lines'))),
            ])
            ->action(function (Sale $record, array $data, $livewire): void {
                $lines = collect($data['lines'] ?? [])->filter(fn (array $line): bool => (int) ($line['quantity'] ?? 0) > 0);

                self::register($record, SaleReturnType::Return, [
                    ...self::moneyPayload($data),
                    'items' => $lines->map(fn (array $line): array => [
                        'sale_item_id' => (int) $line['sale_item_id'],
                        'quantity' => (int) $line['quantity'],
                        'restock' => (bool) ($line['restock'] ?? false),
                    ])->values()->all(),
                ], self::lineKeys($livewire, $lines->keys()->all()), $livewire);
            });
    }

    public static function valueAdjustment(): Action
    {
        return Action::make('valueAdjustment')
            ->label(__('app.sale_return.actions.adjust'))
            ->icon(Heroicon::OutlinedReceiptPercent)
            ->color('warning')
            ->visible(fn (Sale $record): bool => self::canAct($record))
            ->schema(fn (Sale $record): array => [
                TextInput::make('amount')
                    ->label(__('app.fields.amount'))
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->prefix('$')
                    ->live(onBlur: true),
                Textarea::make('reason')
                    ->label(__('app.sale_return.fields.reason'))
                    ->required()
                    ->maxLength(255),
                ...self::moneyFields($record, fn (Get $get): int => self::maxMoney($record, (int) $get('amount'))),
            ])
            ->action(fn (Sale $record, array $data, $livewire) => self::register(
                $record,
                SaleReturnType::ValueAdjustment,
                [...self::moneyPayload($data), 'amount' => (int) $data['amount']],
                [],
                $livewire,
            ));
    }

    public static function voidSale(): Action
    {
        return Action::make('voidSale')
            ->label(fn (Sale $record): string => $record->document_type === SaleDocumentType::Layaway
                ? __('app.sale_return.actions.cancel_layaway')
                : __('app.sale_return.actions.void'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Sale $record): bool => self::canAct($record) && ! $record->is_delivered)
            ->fillForm(function (Sale $record): array {
                $due = self::amountToGiveBack($record);

                return $record->customer_id === null
                    ? ['refund_amount' => $due, 'store_credit_amount' => 0]
                    : ['refund_amount' => 0, 'store_credit_amount' => $due];
            })
            ->schema(fn (Sale $record): array => [
                Textarea::make('reason')
                    ->label(__('app.sale_return.fields.reason'))
                    ->required()
                    ->maxLength(255),
                ...self::moneyFields($record, function () use ($record): string {
                    $fee = $record->cancellationFee();

                    return __('app.sale_return.void_hint', [
                        'amount' => self::money(self::amountToGiveBack($record)),
                        'fee' => $fee > 0 ? __('app.sale_return.fee_note', ['fee' => self::money($fee)]) : '',
                    ]);
                }, isVoid: true),
            ])
            ->action(fn (Sale $record, array $data, $livewire) => self::register($record, SaleReturnType::Void, self::moneyPayload($data), [], $livewire));
    }

    /** Only admins act on a sale after the fact; a voided sale takes nothing more and a quote has nothing to give back. */
    private static function canAct(Sale $sale): bool
    {
        return auth()->user()?->isAdmin() === true
            && $sale->status !== SaleStatus::Voided
            && $sale->document_type !== SaleDocumentType::Quote;
    }

    /**
     * Refund / store-credit fields shared by the three actions, with a helper line saying how much money can go out.
     *
     * @param  callable(Get): (int|string)  $hint  The maximum money out, or the full helper text for a void.
     * @return list<Placeholder|Select|TextInput>
     */
    private static function moneyFields(Sale $sale, callable $hint, bool $isVoid = false): array
    {
        return [
            Placeholder::make('money_hint')
                ->hiddenLabel()
                ->content(function (Get $get) use ($hint, $isVoid): string {
                    $value = $hint($get);

                    return $isVoid ? (string) $value : __('app.sale_return.money_hint', ['max' => self::money((int) $value)]);
                }),
            TextInput::make('refund_amount')
                ->label(__('app.sale_return.fields.refund_amount'))
                ->integer()
                ->minValue(0)
                ->default(0)
                ->prefix('$')
                ->live(onBlur: true),
            Select::make('refund_payment_method_id')
                ->label(__('app.sale_return.fields.refund_method'))
                ->options(fn (): array => PaymentMethod::withoutGlobalScopes()
                    ->where('company_id', $sale->company_id)
                    ->where('is_store_credit', false)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required(fn (Get $get): bool => (int) $get('refund_amount') > 0),
            TextInput::make('store_credit_amount')
                ->label(__('app.sale_return.fields.store_credit_amount'))
                ->integer()
                ->minValue(0)
                ->default(0)
                ->prefix('$')
                ->visible($sale->customer_id !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{reason: string, refund_amount: int, refund_payment_method_id: int|null, store_credit_amount: int}
     */
    private static function moneyPayload(array $data): array
    {
        return [
            'reason' => (string) $data['reason'],
            'refund_amount' => (int) ($data['refund_amount'] ?? 0),
            'refund_payment_method_id' => filled($data['refund_payment_method_id'] ?? null) ? (int) $data['refund_payment_method_id'] : null,
            'store_credit_amount' => (int) ($data['store_credit_amount'] ?? 0),
        ];
    }

    /**
     * Runs the action and, when the data is refused, puts each error under the modal field it belongs to:
     * the form calls the repeater `lines` where the action calls it `items`, keyed by the repeater's own row keys.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<int|string>  $lineKeys  Repeater keys of the rows sent as `items`, in order.
     */
    private static function register(Sale $sale, SaleReturnType $type, array $payload, array $lineKeys, mixed $livewire): void
    {
        try {
            app(RegisterSaleReturn::class)->handle($sale, $type, $payload, auth()->user());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ['mountedActions.0.data.'.self::formKey($key, $lineKeys, $sale) => $messages])
                ->all());
        }

        $sale->refresh();

        if (method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData(['total', 'is_delivered']);
        }

        Notification::make()->success()->title(__('app.sale_return.registered'))->send();
    }

    /**
     * The action receives the repeater as a list, but its form errors are keyed by the repeater's own row keys:
     * translate the list positions of the rows sent into those keys.
     *
     * @param  list<int>  $positions
     * @return list<int|string>
     */
    private static function lineKeys(mixed $livewire, array $positions): array
    {
        $rowKeys = array_keys($livewire->mountedActions[0]['data']['lines'] ?? []);

        return array_map(fn (int $position): int|string => $rowKeys[$position] ?? $position, $positions);
    }

    /** @param  list<int|string>  $lineKeys */
    private static function formKey(string $key, array $lineKeys, Sale $sale): string
    {
        if ($key === 'items') {
            return 'lines';
        }

        // Walk-in sales have no store-credit field, so the money-limit errors go under the refund.
        if ($key === 'store_credit_amount' && $sale->customer_id === null) {
            return 'refund_amount';
        }

        return preg_replace_callback('/^items\.(\d+)\./', fn (array $match): string => 'lines.'.($lineKeys[(int) $match[1]] ?? $match[1]).'.', $key);
    }

    /** @return list<array{sale_item_id: int, description: string, returnable: int, restockable: bool, quantity: int, restock: bool}> */
    private static function returnableLines(Sale $sale): array
    {
        return $sale->items()->with(['product', 'lensConfig'])->get()
            ->filter(fn (SaleItem $item): bool => $item->returnableQuantity() > 0)
            ->map(fn (SaleItem $item): array => [
                'sale_item_id' => $item->id,
                'description' => $item->description,
                'returnable' => $item->returnableQuantity(),
                'restockable' => $item->movesStock() && ! $item->isLens(),
                'quantity' => 0,
                'restock' => false,
            ])
            ->values()
            ->all();
    }

    /** What is paid but no longer owed once the sale's value drops by $returned (capped to what is left of the value). */
    private static function maxMoney(Sale $sale, int $returned): int
    {
        $net = max(0, $sale->netTotal());

        return max(0, $sale->totalPaid() - ($net - min($net, $returned)));
    }

    /**
     * Estimated value of the units picked in the form, priced like the action does (share of the line after the sale discount).
     *
     * @param  array<int|string, array<string, mixed>>  $lines
     */
    private static function maxMoneyForLines(Sale $sale, array $lines): int
    {
        $items = $sale->items->keyBy('id');
        $factor = $sale->chargedFactor();
        $value = collect($lines)->sum(function (array $line) use ($items, $factor): int {
            $item = $items->get((int) ($line['sale_item_id'] ?? 0));

            return $item === null ? 0 : (int) round($item->line_total * max(0, (int) ($line['quantity'] ?? 0)) / $item->quantity * $factor);
        });

        return self::maxMoney($sale, (int) $value);
    }

    private static function amountToGiveBack(Sale $sale): int
    {
        return max(0, $sale->totalPaid() - $sale->cancellationFee());
    }

    private static function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
