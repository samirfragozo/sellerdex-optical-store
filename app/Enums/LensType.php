<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LensType: string implements HasColor, HasLabel
{
    case SingleVision = 'single_vision';
    case ExtendedRange = 'extended_range';
    case Bifocal = 'bifocal';
    case Progressive = 'progressive';

    public function label(): string
    {
        return __('app.lens_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SingleVision => 'gray',
            self::ExtendedRange => 'info',
            self::Bifocal => 'warning',
            self::Progressive => 'success',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
