<?php

namespace App\Enums;

enum CashCloseType: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';

    public function label(): string
    {
        return __('app.cash_close_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
