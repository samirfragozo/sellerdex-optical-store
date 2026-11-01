<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Admin sidebar groups. Case order is the sidebar order: most visited first.
 */
enum NavigationGroup: string implements HasLabel
{
    case Counter = 'counter';
    case Finance = 'finance';
    case Inventory = 'inventory';
    case Settings = 'settings';

    public function getLabel(): string
    {
        return __('app.navigation_groups.'.$this->value);
    }
}
