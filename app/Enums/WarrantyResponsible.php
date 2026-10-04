<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WarrantyResponsible: string implements HasLabel
{
    case Store = 'store';
    case Supplier = 'supplier';

    public function label(): string
    {
        return __('app.warranty_responsible.'.$this->value);
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
