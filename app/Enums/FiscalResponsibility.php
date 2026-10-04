<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FiscalResponsibility: string implements HasLabel
{
    case GranContribuyente = 'O-13';
    case Autorretenedor = 'O-15';
    case AgenteRetencionIva = 'O-23';
    case RegimenSimple = 'O-47';
    case NoResponsable = 'R-99-PN';

    public function label(): string
    {
        return __('app.fiscal_responsibility.'.$this->value);
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
