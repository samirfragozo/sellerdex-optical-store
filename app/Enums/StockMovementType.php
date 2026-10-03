<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case Initial = 'initial';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Purchase = 'purchase';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('app.stock_movement_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Initial => 'info',
            self::Sale => 'gray',
            self::SaleReturn => 'warning',
            self::Purchase => 'success',
            self::Adjustment => 'danger',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
