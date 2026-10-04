<?php

namespace App\Filament\Resources\BusinessSettings;

use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Models\Company;
use BackedEnum;
use Filament\Forms\Components\TextInput;
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
                Toggle::make('blind_cash_count')
                    ->label(__('app.business.blind_cash_count'))
                    ->helperText(__('app.business.blind_cash_count_help')),
                TextInput::make('cash_difference_note_threshold')
                    ->label(__('app.business.cash_difference_note_threshold'))
                    ->helperText(__('app.business.cash_difference_note_threshold_help'))
                    ->integer()
                    ->minValue(0)
                    ->required()
                    ->prefix('$'),
                TextInput::make('layaway_cancellation_fee_percent')
                    ->label(__('app.business.layaway_cancellation_fee_percent'))
                    ->helperText(__('app.business.layaway_cancellation_fee_percent_help'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required()
                    ->suffix('%'),
                TextInput::make('seller_max_discount_percent')
                    ->label(__('app.business.seller_max_discount_percent'))
                    ->helperText(__('app.business.seller_max_discount_percent_help'))
                    ->numeric()->minValue(0)->maxValue(100)->required()->suffix('%'),
                TextInput::make('quote_validity_days')
                    ->label(__('app.business.quote_validity_days'))
                    ->helperText(__('app.business.quote_validity_days_help'))
                    ->integer()->minValue(1)->maxValue(365)->required()->suffix(__('app.fields.days')),
                TextInput::make('adaptation_warranty_days')
                    ->label(__('app.business.adaptation_warranty_days'))
                    ->helperText(__('app.business.adaptation_warranty_days_help'))
                    ->integer()->minValue(0)->maxValue(365)->required()->suffix(__('app.fields.days')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBusinessSetting::route('/'),
        ];
    }
}
