<?php

namespace App\Filament\Resources\Products\Actions;

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Product;
use App\Support\StockLedger;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Set a product's stock to what was physically counted, leaving the reason in the kardex. */
class AdjustStockAction
{
    public static function make(): Action
    {
        return Action::make('adjustStock')
            ->label(__('app.inventory.adjust'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->visible(fn (Product $record): bool => $record->is_stockable && Company::current()->tracksInventory())
            ->authorize('update')
            ->schema(fn (Product $record): array => [
                TextInput::make('counted')
                    ->label(__('app.inventory.counted'))
                    ->helperText(__('app.inventory.current', ['stock' => (int) $record->stock]))
                    ->integer()
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if ((int) $value === (int) $record->fresh()->stock) {
                            $fail(__('app.inventory.same_count'));
                        }
                    }),
                TextInput::make('reason')->label(__('app.inventory.reason'))->required()->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                StockLedger::record($record, StockMovementType::Adjustment, (int) $data['counted'] - (int) $record->fresh()->stock, reason: $data['reason']);
                Notification::make()->success()->title(__('app.inventory.adjusted'))->send();
            });
    }
}
