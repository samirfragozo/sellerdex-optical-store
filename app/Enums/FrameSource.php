<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FrameSource: string implements HasLabel
{
    case Sold = 'sold';
    case CustomerOwn = 'customer_own';

    public function label(): string
    {
        return __('app.frame_source.'.$this->value);
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
