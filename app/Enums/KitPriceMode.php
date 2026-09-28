<?php

namespace App\Enums;

/** How a kit slot product is charged: free, at its price, discounted, or folded into the lens price. */
enum KitPriceMode: string
{
    case Free = 'free';
    case Normal = 'normal';
    case DiscountPercent = 'discount_percent';
    case AddedToLens = 'added_to_lens';

    public function label(): string
    {
        return __('app.kit_price_mode.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
