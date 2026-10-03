<?php

namespace App\Filament\Resources\LensOrders\Actions;

use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Models\LensOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

/** The fixed lab-order flow: pending → sent → received → ready, plus remakes. */
class LabOrderActions
{
    public static function send(): Action
    {
        return Action::make('send')
            ->authorize('update')
            ->label(__('app.lab_order.actions.send'))
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (LensOrder $record): bool => $record->lab_status === LensOrderStatus::PendingAssignment)
            ->requiresConfirmation()
            ->action(function (LensOrder $record): void {
                $missing = $record->missingForSending();

                if ($missing !== []) {
                    Notification::make()->danger()
                        ->title(__('app.lab_order.incomplete'))
                        ->body(__('app.lab_order.missing', ['fields' => implode(', ', $missing)]))
                        ->send();

                    return;
                }

                $record->markSent();
                Notification::make()->success()->title(__('app.lab_order.sent'))->send();
            });
    }

    public static function receive(): Action
    {
        return Action::make('receive')
            ->authorize('update')
            ->label(__('app.lab_order.actions.receive'))
            ->icon('heroicon-o-inbox-arrow-down')
            ->visible(fn (LensOrder $record): bool => $record->lab_status === LensOrderStatus::Sent && ! $record->remakes()->exists())
            ->requiresConfirmation()
            ->action(fn (LensOrder $record) => $record->update([
                'lab_status' => LensOrderStatus::Received,
                'received_date' => now()->toDateString(),
            ]));
    }

    public static function ready(): Action
    {
        return Action::make('markReady')
            ->authorize('update')
            ->label(__('app.lab_order.actions.ready'))
            ->icon('heroicon-o-check-badge')
            ->visible(fn (LensOrder $record): bool => $record->lab_status === LensOrderStatus::Received && ! $record->remakes()->exists())
            ->requiresConfirmation()
            ->action(fn (LensOrder $record) => $record->update(['lab_status' => LensOrderStatus::Ready]));
    }

    public static function remake(): Action
    {
        return Action::make('remake')
            ->authorize('update')
            ->label(__('app.lab_order.actions.remake'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (LensOrder $record): bool => ! in_array($record->lab_status, [LensOrderStatus::PendingAssignment, LensOrderStatus::Cancelled], true) && ! $record->remakes()->exists())
            ->schema([
                Select::make('remake_reason')->label(__('app.fields.remake_reason'))->options(RemakeReason::options())->required(),
                Select::make('remake_responsible')->label(__('app.fields.remake_responsible'))->options(RemakeResponsible::options())->required()->live(),
                TextInput::make('remake_cost')->label(__('app.fields.remake_cost'))->numeric()->minValue(1)->prefix('$')
                    ->helperText(__('app.lab_order.remake_cost_help'))
                    ->visible(fn (Get $get): bool => $get('remake_responsible') === RemakeResponsible::Store->value)
                    ->required(fn (Get $get): bool => $get('remake_responsible') === RemakeResponsible::Store->value),
                Textarea::make('notes')->label(__('app.fields.notes'))->maxLength(1000),
            ])
            ->action(function (LensOrder $record, array $data): void {
                $record->remake(
                    RemakeReason::from($data['remake_reason']),
                    RemakeResponsible::from($data['remake_responsible']),
                    (int) ($data['remake_cost'] ?? 0),
                    notes: $data['notes'] ?? null,
                );
                Notification::make()->success()->title(__('app.lab_order.remade'))->send();
            });
    }
}
