<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LensKind: string implements HasColor, HasLabel
{
    case SingleVision = 'single_vision';
    case ExtendedRange = 'extended_range';
    case Bifocal = 'bifocal';
    case Progressive = 'progressive';

    public function label(): string
    {
        return __('app.lens_kind.'.$this->value);
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

    /** Bifocal and progressive lenses require a prescription with addition. */
    public function requiresAddition(): bool
    {
        return match ($this) {
            self::Bifocal, self::Progressive => true,
            self::SingleVision, self::ExtendedRange => false,
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
