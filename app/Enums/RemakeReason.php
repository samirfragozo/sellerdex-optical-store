<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RemakeReason: string implements HasLabel
{
    case Prescription = 'prescription';
    case Measurements = 'measurements';
    case LabDefect = 'lab_defect';
    case Breakage = 'breakage';
    case NonAdaptation = 'non_adaptation';
    case Other = 'other';

    public function label(): string
    {
        return __('app.remake_reason.'.$this->value);
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
