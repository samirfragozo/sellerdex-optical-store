<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;

/** Final step: the view renders the summary of every other step and "Go sell". */
class SummaryStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'summary';
    }

    public function label(): string
    {
        return __('app.onboarding.summary.label');
    }

    public function description(): string
    {
        return __('app.onboarding.summary.description');
    }

    public function components(): array
    {
        return [];
    }

    public function isComplete(Company $company): bool
    {
        return $company->onboarded_at !== null;
    }

    public function summary(Company $company): string
    {
        return '';
    }
}
