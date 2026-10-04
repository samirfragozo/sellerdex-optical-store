<?php

namespace App\Filament\Resources\BusinessSettings;

use App\Enums\MessageTemplateKey;
use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Filament\Pages\Onboarding\Steps\InvoicingStep;
use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Models\Company;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
                ...InvoicingStep::fields(),
                Section::make(__('app.message_templates.title'))
                    ->description(__('app.message_templates.help'))
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema(collect(MessageTemplateKey::cases())->map(fn (MessageTemplateKey $key) => Textarea::make('templates.'.$key->value)
                        ->label($key->label())
                        ->rows(3)
                        ->maxLength(1000))->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBusinessSetting::route('/'),
        ];
    }
}
