<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Partial = 'partial';
    case Paid = 'paid';
    case Delivered = 'delivered';
    case Voided = 'voided';

    public function label(): string
    {
        return __('app.sale_status.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
