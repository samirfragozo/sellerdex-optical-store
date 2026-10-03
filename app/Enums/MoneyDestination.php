<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MoneyDestination: string implements HasColor, HasLabel
{
    case Refund = 'refund';
    case SaleBalance = 'sale_balance';
    case StoreCredit = 'store_credit';
    case Combined = 'combined';

    /** Where the money of a return goes, given how much is refunded and how much becomes store credit. */
    public static function for(int $refund, int $storeCredit): self
    {
        return match (true) {
            $refund > 0 && $storeCredit > 0 => self::Combined,
            $refund > 0 => self::Refund,
            $storeCredit > 0 => self::StoreCredit,
            default => self::SaleBalance,
        };
    }

    public function label(): string
    {
        return __('app.money_destination.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Refund => 'danger',
            self::SaleBalance => 'gray',
            self::StoreCredit => 'success',
            self::Combined => 'info',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
