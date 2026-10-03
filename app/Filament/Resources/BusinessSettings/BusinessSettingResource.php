<?php

namespace App\Filament\Resources\BusinessSettings;

use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Models\Company;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Single-record ("singleton") resource: the navigation entry opens straight to the
 * one business-settings row; there is no list or create page.
 */
class BusinessSettingResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getNavigationLabel(): string
    {
        return __('app.business.title');
    }

    public static function getModelLabel(): string
    {
        return __('app.business.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.business.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...CompanyStep::fields(),
                Toggle::make('tracks_inventory')
                    ->label(__('app.business.tracks_inventory'))
                    ->helperText(__('app.business.tracks_inventory_help')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBusinessSetting::route('/'),
        ];
    }
}
