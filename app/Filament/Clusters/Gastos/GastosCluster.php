<?php

namespace App\Filament\Clusters\Gastos;

use App\Filament\Clusters\Cluster;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class GastosCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
