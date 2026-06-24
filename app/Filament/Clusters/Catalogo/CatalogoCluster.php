<?php

namespace App\Filament\Clusters\Catalogo;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

class CatalogoCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $clusterBreadcrumb = 'Catálogo';

    public static function getNavigationLabel(): string
    {
        return 'Catálogo';
    }
}
