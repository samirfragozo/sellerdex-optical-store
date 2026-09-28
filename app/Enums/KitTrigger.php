<?php

namespace App\Enums;

/** What makes a kit slot apply: a prescription-glasses armado, a standalone product of a category, a specific product, or every sale. */
enum KitTrigger: string
{
    case Armado = 'armado';
    case Category = 'category';
    case Product = 'product';
    case Sale = 'sale';

    public function label(): string
    {
        return __('app.kit_trigger.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
