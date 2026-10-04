<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\LayawayInvoicing;
use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use App\Models\NumberingRange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class InvoicingStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'invoicing';
    }

    public function label(): string
    {
        return __('app.onboarding.invoicing.label');
    }

    public function description(): string
    {
        return __('app.onboarding.invoicing.description');
    }

    /** Shared with BusinessSettingResource so settings mirror onboarding. */
    public static function fields(): array
    {
        $external = fn (Get $get): bool => $get('invoicing_mode') === InvoicingMode::ExternalManual->value;

        return [
            Radio::make('invoicing_mode')
                ->label(__('app.onboarding.invoicing.mode'))
                ->options([
                    InvoicingMode::ReceiptOnly->value => InvoicingMode::ReceiptOnly->label(),
                    InvoicingMode::ExternalManual->value => InvoicingMode::ExternalManual->label(),
                ])
                ->descriptions([
                    InvoicingMode::ReceiptOnly->value => __('app.onboarding.invoicing.receipt_only_help'),
                    InvoicingMode::ExternalManual->value => __('app.onboarding.invoicing.external_manual_help'),
                ])
                ->in([InvoicingMode::ReceiptOnly->value, InvoicingMode::ExternalManual->value])
                ->required()
                ->live(),
            TextInput::make('receipt_prefix')
                ->label(__('app.onboarding.invoicing.receipt_prefix'))
                ->helperText(__('app.onboarding.invoicing.receipt_prefix_help'))
                ->maxLength(10)
                ->alphaDash()
                ->visible(fn (Get $get): bool => $get('invoicing_mode') === InvoicingMode::ReceiptOnly->value),
            Radio::make('default_fiscal_document')
                ->label(__('app.onboarding.invoicing.default_fiscal_document'))
                ->helperText(__('app.onboarding.invoicing.default_fiscal_document_help'))
                ->options([
                    FiscalDocumentType::PosElectronic->value => FiscalDocumentType::PosElectronic->label(),
                    FiscalDocumentType::ElectronicInvoice->value => FiscalDocumentType::ElectronicInvoice->label(),
                ])
                ->visible($external)
                ->required($external),
            DatePicker::make('pos_resolution_expires_at')
                ->label(__('app.onboarding.invoicing.pos_resolution_expires_at'))
                ->helperText(__('app.onboarding.invoicing.resolution_expires_help'))
                ->visible($external),
            DatePicker::make('invoice_resolution_expires_at')
                ->label(__('app.onboarding.invoicing.invoice_resolution_expires_at'))
                ->visible($external),
            Radio::make('layaway_invoicing')
                ->label(__('app.onboarding.invoicing.layaway_invoicing'))
                ->helperText(__('app.onboarding.invoicing.layaway_invoicing_help'))
                ->options(LayawayInvoicing::options())
                ->required(),
        ];
    }

    public function components(): array
    {
        return self::fields();
    }

    public function fill(Company $company): array
    {
        return [
            'invoicing_mode' => $company->invoicing_mode === InvoicingMode::Undecided ? null : $company->invoicing_mode->value,
            'default_fiscal_document' => $company->default_fiscal_document->value,
            'pos_resolution_expires_at' => $company->pos_resolution_expires_at?->toDateString(),
            'invoice_resolution_expires_at' => $company->invoice_resolution_expires_at?->toDateString(),
            'layaway_invoicing' => $company->layaway_invoicing->value,
            'receipt_prefix' => self::receiptRange($company)?->prefix,
        ];
    }

    public function save(Company $company, array $state): void
    {
        self::persist($company, $state);
    }

    /** Also used by the business settings page, whose form saves the company itself. */
    public static function persist(Company $company, array $state): void
    {
        $company->update(collect($state)->only([
            'invoicing_mode', 'default_fiscal_document', 'pos_resolution_expires_at', 'invoice_resolution_expires_at', 'layaway_invoicing',
        ])->all());

        if (array_key_exists('receipt_prefix', $state)) {
            NumberingRange::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $company->id, 'document_type' => FiscalDocumentType::Receipt->value],
                ['prefix' => filled($state['receipt_prefix']) ? $state['receipt_prefix'] : null],
            );
        }
    }

    public function isComplete(Company $company): bool
    {
        return $company->invoicing_mode !== InvoicingMode::Undecided;
    }

    public function summary(Company $company): string
    {
        return $company->invoicing_mode->label();
    }

    public static function receiptRange(Company $company): ?NumberingRange
    {
        return NumberingRange::withoutGlobalScopes()->where('company_id', $company->id)
            ->where('document_type', FiscalDocumentType::Receipt->value)->first();
    }
}
