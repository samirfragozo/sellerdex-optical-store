<?php

namespace App\Filament\Clusters\Gastos;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class GastosCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $clusterBreadcrumb = 'Gastos';

    public static function getNavigationLabel(): string
    {
        return 'Gastos';
    }
}
