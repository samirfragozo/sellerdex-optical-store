<?php

namespace App\Enums;

enum CashCloseStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Approved = 'approved';

    public function label(): string
    {
        return __('app.cash_close_status.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
