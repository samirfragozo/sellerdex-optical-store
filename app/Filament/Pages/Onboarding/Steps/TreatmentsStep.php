<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TreatmentsStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'treatments';
    }

    public function label(): string
    {
        return __('app.onboarding.treatments.label');
    }

    public function description(): string
    {
        return __('app.onboarding.treatments.description');
    }

    public function components(): array
    {
        return [
            Repeater::make('lensTreatments')
                ->hiddenLabel()
                ->relationship('lensTreatments')
                ->schema([
                    TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
                    TextInput::make('price')->label(__('app.fields.price'))->required()->integer()->minValue(0)->prefix('$'),
                    TextInput::make('cost')->label(__('app.fields.cost'))->required()->integer()->minValue(0)->prefix('$'),
                    Toggle::make('is_active')->label(__('app.fields.active'))->default(true),
                ])
                ->columns(4)
                ->defaultItems(0)
                ->addActionLabel(__('app.onboarding.treatments.add')),
        ];
    }

    /** Treatments are optional: the step is done once the user got here. */
    public function isComplete(Company $company): bool
    {
        return $this->reachedOffset($company) >= 0;
    }

    public function summary(Company $company): string
    {
        return __('app.onboarding.treatments.summary', ['count' => $company->lensTreatments()->where('is_active', true)->count()]);
    }
}
