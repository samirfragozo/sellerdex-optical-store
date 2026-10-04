<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FiscalDocumentType: string implements HasLabel
{
    case Receipt = 'receipt';
    case PosElectronic = 'pos_electronic';
    case ElectronicInvoice = 'electronic_invoice';
    case CreditNote = 'credit_note';
    case AdjustmentNote = 'adjustment_note';

    /** The note that corrects this document: a nota crédito for a factura, a nota de ajuste for a POS document. */
    public function noteFor(): ?self
    {
        return match ($this) {
            self::ElectronicInvoice => self::CreditNote,
            self::PosElectronic => self::AdjustmentNote,
            default => null,
        };
    }

    public function isSaleDocument(): bool
    {
        return in_array($this, [self::PosElectronic, self::ElectronicInvoice], true);
    }

    public function label(): string
    {
        return __('app.fiscal_document_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
