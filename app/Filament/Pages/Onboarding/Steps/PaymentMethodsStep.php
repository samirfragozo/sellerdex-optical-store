<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethodsStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'payment_methods';
    }

    public function label(): string
    {
        return __('app.onboarding.payment_methods.label');
    }

    public function description(): string
    {
        return __('app.onboarding.payment_methods.description');
    }

    public function components(): array
    {
        return [
            TextEntry::make('cash_notice')
                ->hiddenLabel()
                ->state(__('app.onboarding.payment_methods.cash_notice')),
            // Cash (is_default) is fixed and never shown here, so it cannot be removed.
            Repeater::make('paymentMethods')
                ->label(__('app.onboarding.payment_methods.others'))
                ->relationship('paymentMethods', fn (Builder $query) => $query->where('is_default', false))
                ->schema([
                    TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
                    TextInput::make('surcharge_percent')->label(__('app.fields.surcharge_percent'))
                        ->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%'),
                    Toggle::make('is_active')->label(__('app.fields.active'))->default(true),
                ])
                ->columns(3)
                ->defaultItems(0)
                ->addActionLabel(__('app.onboarding.payment_methods.add')),
        ];
    }

    public function isComplete(Company $company): bool
    {
        return $company->paymentMethods()->where('is_active', true)->exists();
    }

    public function summary(Company $company): string
    {
        return $company->paymentMethods()->where('is_active', true)->orderBy('sort_order')->pluck('name')->join(', ');
    }
}
