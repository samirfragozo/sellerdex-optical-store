<?php

namespace App\Filament\Widgets\FollowUp;

use App\Enums\FollowUpReason;
use App\Enums\MessageTemplateKey;
use App\Enums\SaleDocumentType;
use App\Models\MessageTemplate;
use App\Models\Sale;
use App\Support\WhatsApp;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Customers who still owe on a sale; a contact hides the sale for a week, then it comes back. */
class BalancesDueWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return auth()->user()?->company_id !== null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.follow_up.balances_due'))
            ->query(fn (): Builder => Sale::query()
                ->with('customer')
                ->whereHas('customer')
                // A quote owes nothing.
                ->where('sales.document_type', '!=', SaleDocumentType::Quote->value)
                ->outstanding()
                ->whereNotExists(fn ($contact) => $contact->selectRaw('1')->from('follow_up_contacts')
                    ->where('follow_up_contacts.reason', FollowUpReason::BalanceDue->value)
                    ->where('follow_up_contacts.subject_type', (new Sale)->getMorphClass())
                    ->whereColumn('follow_up_contacts.subject_id', 'sales.id')
                    ->where('follow_up_contacts.contacted_at', '>=', now()->subDays(7))))
            ->defaultSort('sold_at')
            ->columns([
                TextColumn::make('number')->label(__('app.fields.number')),
                TextColumn::make('customer.full_name')->label(__('app.fields.customer')),
                TextColumn::make('customer.phone')->label(__('app.fields.phone'))->placeholder('—'),
                TextColumn::make('balance')->label(__('app.fields.balance'))->money('COP'),
                TextColumn::make('sold_at')->label(__('app.fields.sold_at'))->date('d/m/Y'),
            ])
            ->recordActions([
                FollowUpActions::whatsApp(fn (Sale $record): ?string => WhatsApp::url(
                    $record->customer?->phone,
                    MessageTemplate::render($record->company_id, MessageTemplateKey::BalanceDue, [
                        'cliente' => (string) $record->customer?->name, 'orden' => $record->number, 'saldo' => $record->balance,
                    ]),
                )),
                FollowUpActions::markContacted(FollowUpReason::BalanceDue, fn (Sale $record) => $record->customer, fn (Sale $record) => $record),
            ]);
    }
}
