<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RemakeResponsible: string implements HasLabel
{
    case Store = 'store';
    case Lab = 'lab';
    case Customer = 'customer';

    public function label(): string
    {
        return __('app.remake_responsible.'.$this->value);
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
