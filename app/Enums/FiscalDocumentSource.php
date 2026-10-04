<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FiscalDocumentSource: string implements HasLabel
{
    case Internal = 'internal';
    case ExternalManual = 'external_manual';

    public function label(): string
    {
        return __('app.fiscal_document_source.'.$this->value);
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
