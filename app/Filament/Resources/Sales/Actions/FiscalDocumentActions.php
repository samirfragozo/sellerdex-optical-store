<?php

namespace App\Filament\Resources\Sales\Actions;

use App\Actions\RegisterFiscalDocument;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Models\Company;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
