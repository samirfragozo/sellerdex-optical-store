<?php

namespace App\Filament\Resources\CashRegisterSessions\Schemas;

use App\Models\CashRegisterSession;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CashRegisterSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('user.name')->label(__('app.cash_session_admin.cashier')),
                TextEntry::make('opened_at')->label(__('app.cash_session_admin.opened_at'))->dateTime(),
                TextEntry::make('closed_at')->label(__('app.cash_session_admin.closed_at'))->dateTime()->placeholder(__('app.cash_session_admin.open')),
                TextEntry::make('opening_cash')->label(__('app.pos.cash_session.opening_cash'))->money('COP'),
                TextEntry::make('expected_cash')->label(__('app.pos.cash_session.expected'))->money('COP')->placeholder('-'),
                TextEntry::make('closed_cash')->label(__('app.pos.cash_session.counted'))->money('COP')->placeholder('-'),
                TextEntry::make('difference')->label(__('app.pos.cash_session.difference'))->money('COP')->placeholder('-'),
                TextEntry::make('cash_left')->label(__('app.pos.cash_session.cash_left'))->money('COP')->placeholder('-'),
                TextEntry::make('closedBy.name')->label(__('app.cash_session_admin.closed_by'))->placeholder('-'),
                IconEntry::make('closed_by_admin')->label(__('app.cash_session_admin.closed_by_admin'))->boolean(),
                TextEntry::make('reviewed_at')->label(__('app.cash_session_admin.reviewed'))->dateTime()->placeholder('-'),
                TextEntry::make('reviewedBy.name')->label(__('app.cash_session_admin.reviewed_by'))->placeholder('-'),
                TextEntry::make('payments_count')
                    ->label(__('app.cash_session_admin.payments_count'))
                    ->state(fn (CashRegisterSession $record): int => $record->payments()->count()),
                TextEntry::make('payments_total')
                    ->label(__('app.cash_session_admin.payments_total'))
                    ->money('COP')
                    ->state(fn (CashRegisterSession $record): int => (int) $record->payments()->sum('amount')),
                TextEntry::make('needs_note')
                    ->label(__('app.cash_session_admin.needs_note'))
                    ->state(fn (): string => __('app.cash_session_admin.needs_note'))
                    ->badge()
                    ->color('danger')
                    ->visible(fn (CashRegisterSession $record): bool => $record->needsNote()),
                TextEntry::make('notes')->label(__('app.fields.notes'))->placeholder('-')->columnSpanFull(),
            ]),
            Section::make(__('app.cash_session_admin.counts'))
                ->visible(fn (CashRegisterSession $record): bool => $record->counts()->exists())
                ->schema([
                    RepeatableEntry::make('counts')->hiddenLabel()->table([
                        TableColumn::make(__('app.cash_session_admin.method')),
                        TableColumn::make(__('app.pos.cash_session.expected')),
                        TableColumn::make(__('app.pos.cash_session.counted')),
                        TableColumn::make(__('app.pos.cash_session.difference')),
                    ])->schema([
                        TextEntry::make('paymentMethod.name'),
                        TextEntry::make('expected')->money('COP'),
                        TextEntry::make('counted')->money('COP'),
                        TextEntry::make('difference')->money('COP'),
                    ]),
                ]),
            Section::make(__('app.cash_session_admin.movements'))
                ->visible(fn (CashRegisterSession $record): bool => $record->movements()->exists())
                ->schema([
                    RepeatableEntry::make('movements')->hiddenLabel()->table([
                        TableColumn::make(__('app.cash_session_admin.movement_type')),
                        TableColumn::make(__('app.fields.amount')),
                        TableColumn::make(__('app.cash_session_admin.movement_reason')),
                        TableColumn::make(__('app.cash_session_admin.movement_user')),
                        TableColumn::make(__('app.cash_session_admin.movement_time')),
                    ])->schema([
                        TextEntry::make('type')->badge(),
                        TextEntry::make('amount')->money('COP'),
                        TextEntry::make('reason'),
                        TextEntry::make('user.name'),
                        TextEntry::make('created_at')->dateTime(),
                    ]),
                ]),
        ]);
    }
}
