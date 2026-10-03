<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FrameType: string implements HasLabel
{
    case FullRim = 'full_rim';
    case SemiRimless = 'semi_rimless';
    case Rimless = 'rimless';

    public function label(): string
    {
        return __('app.frame_type.'.$this->value);
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
}
