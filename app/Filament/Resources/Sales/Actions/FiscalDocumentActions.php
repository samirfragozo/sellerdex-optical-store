<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Actions\RegisterFiscalDocument;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Models\Company;
use App\Models\Sale;
use App\Support\InvoiceData;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/** External-manual invoicing: record the factura / POS electrónico the shop issued in its own system. */
class FiscalDocumentActions
{
    public static function registerForSale(): Action
    {
        return Action::make('registerFiscalDocument')
            ->label(__('app.fiscal_document.actions.register'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->visible(fn (Sale $record): bool => self::externalMode($record) && $record->isInvoiceableNow() && $record->saleDocument() === null)
            ->fillForm(fn (Sale $record): array => [
                'document_type' => Company::withoutGlobalScopes()->find($record->company_id)?->default_fiscal_document?->value,
                'issued_at' => today()->toDateString(),
            ])
            ->schema(self::fields(withType: true))
            ->action(function (Sale $record, array $data): void {
                self::mapErrors(fn () => app(RegisterFiscalDocument::class)->forSale(
                    $record,
                    FiscalDocumentType::from($data['document_type']),
                    (string) $data['number'],
                    CarbonImmutable::parse($data['issued_at']),
                    auth()->user(),
                    $data['cufe_or_cude'] ?? null,
                    $data['pdf_path'] ?? null,
                ));

                Notification::make()->success()->title(__('app.fiscal_document.registered'))->send();
            });
    }

    public static function invoiceData(): Action
    {
        return Action::make('invoiceData')
            ->label(__('app.invoice_data.title'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->visible(fn (Sale $record): bool => self::externalMode($record) && $record->isInvoiceableNow())
            ->modalHeading(__('app.invoice_data.title'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('app.invoice_data.close'))
            ->schema(fn (Sale $record): array => self::invoiceDataEntries(InvoiceData::for($record)));
    }

    /**
     * Read-only, click-to-copy entries: money shows formatted but copies the raw integer.
     *
     * @param  array{buyer: array<string, ?string>, lines: list<array<string, mixed>>, totals: array<string, int>}  $data
     * @return list<Section>
     */
    private static function invoiceDataEntries(array $data): array
    {
        $text = fn (string $name, string $label, ?string $value): TextEntry => TextEntry::make($name)
            ->label($label)->state($value)->placeholder('-')->copyable();
        $money = fn (string $name, string $label, int $value): TextEntry => TextEntry::make($name)
            ->label($label)->state('$'.number_format($value, 0, ',', '.'))->copyable()->copyableState((string) $value);

        $buyer = $data['buyer'];
        $sections = [
            Section::make(__('app.invoice_data.buyer'))->columns(2)->schema([
                $text('buyer_document_type', __('app.fields.document_type'), $buyer['document_type']),
                $text('buyer_id_number', __('app.fields.id_number'), $buyer['id_number']),
                $text('buyer_name', __('app.fields.full_name'), $buyer['name']),
                $text('buyer_phone', __('app.fields.phone'), $buyer['phone']),
                $text('buyer_email', __('app.fields.email'), $buyer['email']),
                $text('buyer_address', __('app.fields.address'), $buyer['address']),
            ]),
        ];

        foreach ($data['lines'] as $index => $line) {
            $sections[] = Section::make($index === 0 ? __('app.invoice_data.lines') : null)->columns(3)->schema([
                $text("line_{$index}_description", __('app.fields.description'), $line['description'])->columnSpanFull(),
                $text("line_{$index}_quantity", __('app.fields.quantity'), (string) $line['quantity']),
                $money("line_{$index}_unit_price", __('app.invoice_data.unit_price'), $line['unit_price']),
                $money("line_{$index}_base", __('app.invoice_data.base'), $line['base']),
                $money("line_{$index}_tax", __('app.invoice_data.tax'), $line['tax']),
                $money("line_{$index}_total", __('app.fields.total'), $line['total']),
            ]);
        }

        $sections[] = Section::make(__('app.invoice_data.totals'))->columns(3)->schema([
            $money('totals_base', __('app.invoice_data.base'), $data['totals']['base']),
            $money('totals_tax', __('app.invoice_data.tax'), $data['totals']['tax']),
            $money('totals_total', __('app.fields.total'), $data['totals']['total']),
        ]);

        return $sections;
    }

    /** @return list<Field> */
    public static function fields(bool $withType): array
    {
        return array_values(array_filter([
            $withType ? Select::make('document_type')
                ->label(__('app.fiscal_document.fields.document_type'))
                ->options([
                    FiscalDocumentType::PosElectronic->value => FiscalDocumentType::PosElectronic->label(),
                    FiscalDocumentType::ElectronicInvoice->value => FiscalDocumentType::ElectronicInvoice->label(),
                ])
                ->required() : null,
            TextInput::make('number')->label(__('app.fiscal_document.fields.number'))->helperText(__('app.fiscal_document.fields.number_help'))->required()->maxLength(50),
            DatePicker::make('issued_at')->label(__('app.fiscal_document.fields.issued_at'))->maxDate(today())->required(),
            TextInput::make('cufe_or_cude')->label(__('app.fiscal_document.fields.cufe_or_cude'))->maxLength(120),
            FileUpload::make('pdf_path')
                ->label(__('app.fiscal_document.fields.pdf'))
                ->disk('local')
                ->directory('fiscal-documents')
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(5120),
        ]));
    }

    /** Puts the action's validation errors under the modal fields. */
    public static function mapErrors(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ['mountedActions.0.data.'.$key => $messages])
                ->all());
        }
    }

    public static function externalMode(Sale $sale): bool
    {
        return Company::withoutGlobalScopes()->whereKey($sale->company_id)->value('invoicing_mode') === InvoicingMode::ExternalManual;
    }
}
