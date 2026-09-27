<?php

namespace App\Enums;

enum VatRegime: string
{
    case Responsible = 'responsible';
    case NotResponsible = 'not_responsible';

    public function label(): string
    {
        return __('app.vat_regime.'.$this->value);
    }

    /** @return array<string,string> value => label, for Filament selects. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
