<?php

namespace App\Filament\Pages\Onboarding;

use App\Filament\Pages\Onboarding;
use App\Models\Company;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * One onboarding step. The same fields back the matching "Configuración"
 * section later, so what the user learns here is the map of the settings.
 */
abstract class OnboardingStep
{
    abstract public static function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** @return array<int, Component|Field> */
    abstract public function components(): array;

    /** @return array<string, mixed> */
    public function fill(Company $company): array
    {
        return [];
    }

    /** @param  array<string, mixed>  $state */
    public function save(Company $company, array $state): void {}

    abstract public function isComplete(Company $company): bool;

    /** One-line summary shown on the final "ready to sell" step. */
    abstract public function summary(Company $company): string;

    /** Where the company's furthest reached step sits relative to this one: negative before, 0 here, positive past. */
    protected function reachedOffset(Company $company): int
    {
        $keys = array_map(fn (string $class) => $class::key(), Onboarding::steps());
        $reached = array_search($company->onboarding_step, $keys, true);

        if ($reached === false) {
            return $company->onboarded_at !== null ? 1 : -1;
        }

        return $reached - (int) array_search(static::key(), $keys, true);
    }
}
