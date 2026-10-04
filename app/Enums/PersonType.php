<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PersonType: string implements HasLabel
{
    case Natural = 'natural';
    case Legal = 'legal';

    public function label(): string
    {
        return __('app.person_type.'.$this->value);
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
