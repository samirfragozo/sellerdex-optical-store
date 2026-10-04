<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FiscalDocumentStatus: string implements HasLabel
{
    case Registered = 'registered';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return __('app.fiscal_document_status.'.$this->value);
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
