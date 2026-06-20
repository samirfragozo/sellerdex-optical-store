<?php

namespace App\Enums;

enum DocumentType: string
{
    case CC = 'cc';
    case TI = 'ti';
    case CE = 'ce';
    case PA = 'pa';
    case NIT = 'nit';

    public function label(): string
    {
        return __('app.document_type.'.$this->value);
    }

    /** @return array<string,string> value => label, for Filament selects. */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
