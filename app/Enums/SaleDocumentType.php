<?php

namespace App\Enums;

enum SaleDocumentType: string
{
    case Quote = 'quote';
    case Order = 'order';
    case Layaway = 'layaway';
    case Remission = 'remission';
    case Billing = 'billing';

    public function label(): string
    {
        return __('app.sale_document_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
