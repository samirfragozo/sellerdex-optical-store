<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LayawayInvoicing: string implements HasLabel
{
    case OnDelivery = 'on_delivery';
    case OnSale = 'on_sale';

    public function label(): string
    {
        return __('app.layaway_invoicing.'.$this->value);
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
