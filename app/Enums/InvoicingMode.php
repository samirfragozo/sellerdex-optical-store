<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoicingMode: string implements HasLabel
{
    case Undecided = 'undecided';
    case ReceiptOnly = 'receipt_only';
    case ExternalManual = 'external_manual';

    public function label(): string
    {
        return __('app.invoicing_mode.'.$this->value);
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
