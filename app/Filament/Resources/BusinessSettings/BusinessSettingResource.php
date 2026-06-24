<?php

namespace App\Filament\Resources\BusinessSettings;

use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Models\BusinessSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Single-record ("singleton") resource: the navigation entry opens straight to the
 * one business-settings row; there is no list or create page.
 */
class BusinessSettingResource extends Resource
{
    protected static ?string $model = BusinessSetting::class;

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
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                TextInput::make('tax_id')
                    ->label(__('app.fields.tax_id')),
                TextInput::make('address')
                    ->label(__('app.fields.address')),
                TextInput::make('phones')
                    ->label(__('app.fields.phone')),
                FileUpload::make('logo')
                    ->label(__('app.fields.logo'))
                    ->image()
                    ->disk('public')
                    ->directory('business'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBusinessSetting::route('/'),
        ];
    }
}
