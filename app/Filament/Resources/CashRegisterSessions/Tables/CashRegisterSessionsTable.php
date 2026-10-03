<?php

namespace App\Filament\Resources\CashRegisterSessions\Tables;

use App\Actions\CloseCashRegisterSession;
use App\Filament\Resources\CashRegisterSessions\Pages\ViewCashRegisterSession;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\PaymentMethod;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CashRegisterSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label(__('app.cash_session_admin.cashier'))->searchable(),
                TextColumn::make('opened_at')->label(__('app.cash_session_admin.opened_at'))->dateTime()->sortable(),
                TextColumn::make('closed_at')
                    ->label(__('app.cash_session_admin.closed_at'))
                    ->dateTime()
                    ->placeholder(__('app.cash_session_admin.open'))
                    ->badge(fn (CashRegisterSession $record): bool => $record->closed_at === null)
                    ->color(fn (CashRegisterSession $record): ?string => $record->closed_at === null ? 'warning' : null)
                    ->sortable(),
                TextColumn::make('expected_cash')->label(__('app.pos.cash_session.expected'))->money('COP')->sortable(),
                TextColumn::make('closed_cash')->label(__('app.pos.cash_session.counted'))->money('COP')->sortable(),
                TextColumn::make('difference')
                    ->label(__('app.pos.cash_session.difference'))
                    ->money('COP')
                    ->color(fn (?int $state): string => match (true) {
                        $state === null || $state === 0 => 'gray',
                        $state > 0 => 'warning',
                        default => 'danger',
                    })
                    ->icon(fn (?int $state): ?Heroicon => match (true) {
                        $state === null => null,
                        $state === 0 => Heroicon::OutlinedCheckCircle,
                        $state > 0 => Heroicon::OutlinedArrowTrendingUp,
                        default => Heroicon::OutlinedArrowTrendingDown,
                    })
                    ->sortable(),
                IconColumn::make('closed_by_admin')->label(__('app.cash_session_admin.closed_by_admin'))->boolean(),
                IconColumn::make('reviewed_at')->label(__('app.cash_session_admin.reviewed'))->boolean(),
                TextColumn::make('needs_note')
                    ->label(__('app.cash_session_admin.needs_note'))
                    ->state(fn (CashRegisterSession $record): ?string => $record->needsNote() ? __('app.cash_session_admin.needs_note') : null)
                    ->badge()
                    ->color('danger'),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                Filter::make('open')->label(__('app.cash_session_admin.only_open'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('closed_at')),
                Filter::make('closed')->label(__('app.cash_session_admin.only_closed'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('closed_at')),
                Filter::make('unreviewed')->label(__('app.cash_session_admin.only_unreviewed'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('closed_at')->whereNull('reviewed_at')),
                Filter::make('needs_note')->label(__('app.cash_session_admin.only_needs_note'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('closed_at')
                        ->where(fn (Builder $query) => $query->whereNull('notes')->orWhere('notes', ''))
                        ->whereHas('counts', fn (Builder $query) => $query->whereRaw('abs(difference) > ?', [(int) Company::current()->cash_difference_note_threshold]))),
            ])
            ->recordActions([
                ViewAction::make(),
                self::reviewAction(),
                self::reportAction(),
            ])
            ->recordUrl(fn (CashRegisterSession $record): string => ViewCashRegisterSession::getUrl(['record' => $record]));
    }

    public static function reviewAction(): Action
    {
        return Action::make('review')
            ->label(__('app.cash_session_admin.review'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->authorize('update')
            ->visible(fn (CashRegisterSession $record): bool => $record->closed_at !== null && $record->reviewed_at === null)
            ->action(function (CashRegisterSession $record): void {
                $record->update(['reviewed_at' => now(), 'reviewed_by' => auth()->id()]);

                Notification::make()->success()->title(__('app.cash_session_admin.reviewed_done'))->send();
            });
    }

    public static function reportAction(): Action
    {
        return Action::make('report')
            ->label(__('app.cash_session_admin.report'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (CashRegisterSession $record): string => route('documents.cash-session', $record))
            ->openUrlInNewTab();
    }

    /** Admins always see the expected amount, so a difference over the threshold always needs a note, blind mode or not. */
    public static function adminCloseAction(): Action
    {
        return Action::make('adminClose')
            ->label(__('app.cash_session_admin.admin_close'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->authorize('update')
            ->visible(fn (CashRegisterSession $record): bool => $record->closed_at === null)
            ->schema(function (CashRegisterSession $record): array {
                $methods = PaymentMethod::withoutGlobalScopes()->whereIn('id', array_keys($record->expectedByMethod()))->pluck('name', 'id');

                return [
                    ...collect($record->expectedByMethod())->map(fn (int $expected, int $id): TextInput => TextInput::make("counts.{$id}")
                        ->label($methods[$id] ?? (string) $id)
                        ->helperText(__('app.cash_session_admin.expected_hint', ['amount' => '$'.number_format($expected, 0, ',', '.')]))
                        ->integer()->minValue(0)->required()->prefix('$')->live(onBlur: true))->values()->all(),
                    TextInput::make('cash_left')
                        ->label(__('app.pos.cash_session.cash_left'))
                        ->helperText(__('app.pos.cash_session.cash_left_hint'))
                        ->integer()->minValue(0)->default(0)->required()->prefix('$'),
                    Textarea::make('notes')
                        ->label(__('app.fields.notes'))
                        ->maxLength(1000)
                        ->required(fn (Get $get): bool => self::differsOverThreshold($record, (array) $get('counts'))),
                ];
            })
            ->action(function (CashRegisterSession $record, array $data): void {
                try {
                    app(CloseCashRegisterSession::class)->handle($record, $data['counts'], (int) $data['cash_left'], $data['notes'] ?? null, auth()->user());
                } catch (ValidationException $exception) {
                    // Show the action's own error keys under the modal fields.
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn (array $messages, string $key): array => ["mountedActions.0.data.{$key}" => $messages])->all());
                }

                Notification::make()->success()->title(__('app.cash_session_admin.closed'))->send();
            });
    }

    /** @param  array<int|string, mixed>  $counts */
    private static function differsOverThreshold(CashRegisterSession $session, array $counts): bool
    {
        $threshold = (int) Company::withoutGlobalScopes()->whereKey($session->company_id)->value('cash_difference_note_threshold');

        return collect($session->expectedByMethod())
            ->contains(fn (int $expected, int $id): bool => abs((int) ($counts[$id] ?? 0) - $expected) > $threshold);
    }
}
