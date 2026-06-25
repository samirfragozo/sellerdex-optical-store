<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleStatus: string implements HasColor, HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Delivered => 'info',
            self::Voided => 'danger',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
