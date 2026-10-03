<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleReturnType: string implements HasColor, HasLabel
{
    case Return = 'return';
    case ValueAdjustment = 'value_adjustment';
    case Void = 'void';

    public function label(): string
    {
        return __('app.sale_return_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Return => 'warning',
            self::ValueAdjustment => 'info',
            self::Void => 'danger',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
