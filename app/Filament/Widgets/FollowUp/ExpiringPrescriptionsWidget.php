<?php

namespace App\Filament\Widgets\FollowUp;

use App\Enums\FollowUpReason;
use App\Enums\MessageTemplateKey;
use App\Models\MessageTemplate;
use App\Models\Prescription;
use App\Support\WhatsApp;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Each customer's latest prescription when it expires within 30 days or expired in the last 30, until contacted. */
class ExpiringPrescriptionsWidget extends TableWidget
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
            ->heading(__('app.follow_up.expiring_prescriptions'))
            ->query(fn (): Builder => Prescription::query()
                ->with('customer')
                ->whereHas('customer')
                ->whereBetween('expires_at', [today()->subDays(30), today()->addDays(30)])
                // Only the customer's latest prescription: a renewed exam silences the old one.
                ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('prescriptions as newer')
                    ->whereColumn('newer.customer_id', 'prescriptions.customer_id')
                    ->whereNull('newer.deleted_at')
                    ->where(fn ($q) => $q->whereColumn('newer.exam_date', '>', 'prescriptions.exam_date')
                        ->orWhere(fn ($q) => $q->whereColumn('newer.exam_date', 'prescriptions.exam_date')->whereColumn('newer.id', '>', 'prescriptions.id'))))
                ->whereNotExists(fn ($contact) => $contact->selectRaw('1')->from('follow_up_contacts')
                    ->where('follow_up_contacts.reason', FollowUpReason::PrescriptionExpiring->value)
                    ->where('follow_up_contacts.subject_type', (new Prescription)->getMorphClass())
                    ->whereColumn('follow_up_contacts.subject_id', 'prescriptions.id')))
            ->defaultSort('expires_at')
            ->columns([
                TextColumn::make('customer.full_name')->label(__('app.fields.customer')),
                TextColumn::make('customer.phone')->label(__('app.fields.phone'))->placeholder('—'),
                TextColumn::make('expires_at')->label(__('app.fields.expires_at'))->date('d/m/Y'),
            ])
            ->recordActions([
                FollowUpActions::whatsApp(fn (Prescription $record): ?string => WhatsApp::url(
                    $record->customer?->phone,
                    MessageTemplate::render($record->company_id, MessageTemplateKey::PrescriptionExpiring, ['cliente' => (string) $record->customer?->name]),
                )),
                FollowUpActions::markContacted(FollowUpReason::PrescriptionExpiring, fn (Prescription $record) => $record->customer, fn (Prescription $record) => $record),
            ]);
    }
}
