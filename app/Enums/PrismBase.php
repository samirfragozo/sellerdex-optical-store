<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PrismBase: string implements HasLabel
{
    case Up = 'up';
    case Down = 'down';
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return __('app.prism_base.'.$this->value);
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
