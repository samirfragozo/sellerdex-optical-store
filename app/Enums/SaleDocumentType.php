<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleDocumentType: string implements HasColor, HasLabel
{
    case Quote = 'quote';
    case Order = 'order';
    case Layaway = 'layaway';

    public function label(): string
    {
        return __('app.sale_document_type.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Quote => 'gray',
            self::Order => 'success',
            self::Layaway => 'warning',
        };
    }

    /** Short legend printed on the document for this type. */
    public function legend(): string
    {
        return __('app.sale_document_legend.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
