<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MixedRangePricing: string implements HasLabel
{
    case Highest = 'highest';
    case HalfEach = 'half_each';

    public function label(): string
    {
        return __('app.mixed_range_pricing.'.$this->value);
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

    /** Price of a pair whose eyes fall in different price ranges. */
    public function combine(int $od, int $os): int
    {
        return match ($this) {
            self::Highest => max($od, $os),
            self::HalfEach => (int) ceil(($od + $os) / 2),
        };
    }
}
