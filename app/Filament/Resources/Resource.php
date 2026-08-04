<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource as BaseResource;
use Illuminate\Support\Str;

/**
 * Base resource that derives model/plural/navigation labels from the model's
 * class name, looking them up in `lang/{locale}/app.php` under
 * `resources.{snake_case_model_name}.{label|plural|nav}`.
 */
abstract class Resource extends BaseResource
{
    protected static function translationKey(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.'.static::translationKey().'.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.'.static::translationKey().'.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.resources.'.static::translationKey().'.nav');
    }
}
