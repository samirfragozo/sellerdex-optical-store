<?php

namespace App\Enums;

/** How DIAN classifies a tax on a line: taxed at a rate, exempt (taxed at 0 %), or excluded (outside VAT). */
enum TaxTreatment: string
{
    case Taxed = 'taxed';
    case Exempt = 'exempt';
    case Excluded = 'excluded';

    public function label(): string
    {
        return __('app.tax_treatment.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
