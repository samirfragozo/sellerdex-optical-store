<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WarrantyClaimType: string implements HasLabel
{
    case Warranty = 'warranty';
    case Adaptation = 'adaptation';

    public function label(): string
    {
        return __('app.warranty_claim_type.'.$this->value);
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
