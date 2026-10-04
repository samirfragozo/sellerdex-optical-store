<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleDocumentType;
use App\Models\Company;
use App\Models\Sale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('app.fields.customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->options(SaleDocumentType::options())
                    ->default(SaleDocumentType::Order->value)
                    ->required()
                    // Locked after creation: changing it would desync stock and numbering.
                    ->disabledOn('edit')
                    ->dehydrated(),
                DatePicker::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->default(now())
                    ->required(),
                TextInput::make('discount_percent')
                    ->label(__('app.fields.discount_percent'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(fn (): float => auth()->user()?->isAdmin() ? 100 : (float) Company::current()->seller_max_discount_percent)
                    ->helperText(fn (): ?string => auth()->user()?->isAdmin() ? null : __('app.sale_actions.discount_cap_help', ['percent' => (float) Company::current()->seller_max_discount_percent]))
                    ->suffix('%')
                    // The charged value is frozen once returns or a void exist; a discount above the cap was
                    // approved by an admin, so only an admin may change it again.
                    ->disabled(fn (?Sale $record): bool => ($record?->isLockedForEdits() ?? false)
                        || ($record !== null && auth()->user()?->isAdmin() !== true
                            && (float) $record->discount_percent > (float) Company::current()->seller_max_discount_percent)),
                Placeholder::make('total')
                    ->label(__('app.fields.total'))
                    ->visibleOn('edit')
                    ->content(fn (?Sale $record): string => $record ? '$'.number_format($record->total, 0, ',', '.') : '—'),
                Placeholder::make('balance')
                    ->label(__('app.fields.balance'))
                    ->visibleOn('edit')
                    ->content(fn (?Sale $record): string => $record ? '$'.number_format($record->balance, 0, ',', '.') : '—'),
                Placeholder::make('discount_approved_by')
                    ->label(__('app.fields.discount_approved_by'))
                    ->visible(fn (?Sale $record): bool => $record?->discount_approved_by !== null)
                    ->content(fn (?Sale $record): string => (string) $record?->discountApprover?->name),
                Placeholder::make('real_margin')
                    ->label(__('app.fields.real_margin'))
                    // A record only exists when editing; costs are internal, so only admins see the margin.
                    // (visible() replaces visibleOn(), so the record check stands in for the 'edit' operation.)
                    ->visible(fn (?Sale $record): bool => $record !== null && auth()->user()?->isAdmin() === true)
                    ->content(fn (?Sale $record): string => $record ? '$'.number_format($record->realMargin(), 0, ',', '.') : '—'),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
                Section::make(__('app.sections.options'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_delivered')
                            ->label(__('app.fields.is_delivered'))
                            ->disabled(fn (?Sale $record): bool => $record !== null && ! $record->canBeDelivered() && ! $record->is_delivered)
                            ->helperText(fn (?Sale $record): ?string => ($record && ! $record->canBeDelivered() && ! $record->is_delivered) ? __('app.sale_actions.cannot_deliver_pending_lens') : null),
                        DatePicker::make('delivered_at')
                            ->label(__('app.fields.delivered_at'))
                            ->default(now())
                            ->visible(fn ($get): bool => (bool) $get('is_delivered')),
                    ]),
            ]);
    }
}
