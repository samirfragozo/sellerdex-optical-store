<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster as BaseCluster;
use Illuminate\Support\Str;

/**
 * Base cluster that derives the navigation label/breadcrumb from the class
 * name (e.g. `ComprasCluster` -> `compras`), looking it up in
 * `lang/{locale}/app.php` under `clusters.{snake_case_key}`.
 */
abstract class Cluster extends BaseCluster
{
    protected static function translationKey(): string
    {
        return Str::snake((string) str(class_basename(static::class))->beforeLast('Cluster'));
    }

    public static function getNavigationLabel(): string
    {
        return __('app.clusters.'.static::translationKey());
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('app.clusters.'.static::translationKey());
    }
}
