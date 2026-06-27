<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LensOrderStatus: string implements HasColor, HasLabel
{
    case Sent = 'sent';
    case InProcess = 'in_process';
    case Received = 'received';

    public function label(): string
    {
        return __('app.lens_order_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sent => 'gray',
            self::InProcess => 'warning',
            self::Received => 'success',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
