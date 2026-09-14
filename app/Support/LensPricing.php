<?php

namespace App\Support;

/**
 * Retail pricing rule for lenses: cost×multiplier, floored by the chosen
 * filter's minimum tier price (a lens with an expensive filter never sells
 * below that floor, regardless of how cheap its base cost is).
 */
class LensPricing
{
    public const MARKUP_MULTIPLIER = 4;

    /** @var array<string,int> */
    public const TIER_FLOORS = [
        'Sin Filtro' => 125000,
        'Blue Cut' => 195000,
        'Foto Blue Cut' => 295000,
    ];

    public static function price(int $cost, ?string $filter): int
    {
        $markup = (int) (round($cost * self::MARKUP_MULTIPLIER / 1000) * 1000);

        return max($markup, self::TIER_FLOORS[$filter] ?? 0);
    }
}
