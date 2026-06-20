<?php

namespace App\Enums;

enum LensType: string
{
    case SingleVision = 'single_vision';
    case ExtendedRange = 'extended_range';
    case Bifocal = 'bifocal';
    case Progressive = 'progressive';

    public function label(): string
    {
        return __('app.lens_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
