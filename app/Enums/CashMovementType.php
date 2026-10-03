<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CashMovementType: string implements HasColor, HasLabel
{
    case Income = 'income';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return __('app.cash_movement_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Income => 'success',
            self::Withdrawal => 'warning',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
