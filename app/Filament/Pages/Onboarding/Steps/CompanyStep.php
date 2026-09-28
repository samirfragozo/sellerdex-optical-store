<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;

class CompanyStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'company';
    }

    public function label(): string
    {
        return __('app.onboarding.company.label');
    }

    public function description(): string
    {
        return __('app.onboarding.company.description');
    }

    /** Shared with BusinessSettingResource so settings mirror onboarding. */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
            TextInput::make('tax_id')->label(__('app.fields.tax_id'))->required()->maxLength(20),
            Radio::make('vat_regime')
                ->label(__('app.onboarding.company.vat_regime'))
                ->helperText(__('app.onboarding.company.vat_regime_help'))
                ->options(VatRegime::options())
                ->required(),
            TextInput::make('address')->label(__('app.fields.address'))->maxLength(255),
            TextInput::make('phones')->label(__('app.fields.phones'))->maxLength(255),
            TextInput::make('sale_number_prefix')
                ->label(__('app.onboarding.company.sale_number_prefix'))
                ->helperText(__('app.onboarding.company.sale_number_prefix_help'))
                ->maxLength(10)
                ->alphaDash(),
            TextInput::make('prescription_validity_months')
                ->label(__('app.fields.prescription_validity_months'))
                ->integer()
                ->minValue(1)
                // Must fit within the 2-year exam-date window (see PrescriptionForm's exam_date minDate).
                ->maxValue(24)
                ->placeholder('12')
                // ponytail: optional in onboarding; a cleared value falls back to the column default.
                ->dehydrateStateUsing(fn (mixed $state): int => filled($state) ? (int) $state : 12),
            FileUpload::make('logo')->label(__('app.fields.logo'))->image()->disk('public')->directory('business'),
        ];
    }

    public function components(): array
    {
        return self::fields();
    }

    public function fill(Company $company): array
    {
        return [
            ...$company->only(['name', 'tax_id', 'address', 'phones', 'sale_number_prefix', 'prescription_validity_months', 'logo']),
            'vat_regime' => $company->vat_regime?->value,
        ];
    }

    public function save(Company $company, array $state): void
    {
        $company->update(collect($state)->only(['name', 'tax_id', 'vat_regime', 'address', 'phones', 'sale_number_prefix', 'prescription_validity_months', 'logo'])->all());
    }

    public function isComplete(Company $company): bool
    {
        return filled($company->name) && filled($company->tax_id);
    }

    public function summary(Company $company): string
    {
        return "{$company->name} · ".__('app.fields.tax_id')." {$company->tax_id} · {$company->vat_regime->label()}";
    }
}
