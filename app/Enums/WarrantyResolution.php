<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WarrantyResolution: string implements HasLabel
{
    case Repair = 'repair';
    case SameReplacement = 'same_replacement';
    case OtherReplacement = 'other_replacement';
    case Refund = 'refund';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('app.warranty_resolution.'.$this->value);
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
