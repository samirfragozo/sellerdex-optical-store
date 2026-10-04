<?php

namespace App\Filament\Widgets\FollowUp;

use App\Enums\FollowUpReason;
use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Support\WhatsApp;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Customers whose birthday is today, until greeted this year. ponytail: 29 February birthdays are skipped in other years. */
class BirthdaysWidget extends TableWidget
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
            ->heading(__('app.follow_up.birthdays'))
            ->query(fn (): Builder => Customer::query()
                ->whereMonth('birth_date', today()->month)
                ->whereDay('birth_date', today()->day)
                ->whereNotExists(fn ($contact) => $contact->selectRaw('1')->from('follow_up_contacts')
                    ->where('follow_up_contacts.reason', FollowUpReason::Birthday->value)
                    ->whereColumn('follow_up_contacts.customer_id', 'customers.id')
                    ->where('follow_up_contacts.contacted_at', '>=', today()->startOfYear())))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('full_name')->label(__('app.fields.customer')),
                TextColumn::make('phone')->label(__('app.fields.phone'))->placeholder('—'),
                TextColumn::make('age')->label(__('app.fields.age'))->placeholder('—'),
            ])
            ->recordActions([
                FollowUpActions::whatsApp(fn (Customer $record): ?string => WhatsApp::url(
                    $record->phone,
                    MessageTemplate::render($record->company_id, MessageTemplateKey::Birthday, ['cliente' => $record->name]),
                )),
                FollowUpActions::markContacted(FollowUpReason::Birthday, fn (Customer $record) => $record, fn (Customer $record) => $record),
            ]);
    }
}
