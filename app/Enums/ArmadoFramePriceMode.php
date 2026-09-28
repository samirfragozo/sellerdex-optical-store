<?php

namespace App\Enums;

/** How a company charges the frame inside an armado. */
enum ArmadoFramePriceMode: string
{
    case Included = 'included';
    case Normal = 'normal';
    case DiscountPercent = 'discount_percent';

    public function label(): string
    {
        return __('app.armado_frame_price_mode.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
