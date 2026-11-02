<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class LaboratoriesStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'laboratories';
    }

    public function label(): string
    {
        return __('app.onboarding.laboratories.label');
    }

    public function description(): string
    {
        return __('app.onboarding.laboratories.description');
    }

    public function components(): array
    {
        return [
            Repeater::make('laboratories')
                ->hiddenLabel()
                ->relationship('laboratories')
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'is_laboratory' => true, 'is_active' => true])
                ->schema([
                    TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
                    TextInput::make('phone')->label(__('app.fields.phone'))->tel()->maxLength(255),
                    TextInput::make('lead_time_days')->label(__('app.fields.lead_time_days'))
                        ->helperText(__('app.onboarding.laboratories.lead_time_help'))
                        ->integer()->minValue(0)->maxValue(90)->suffix(__('app.onboarding.laboratories.days'))
                        ->requiredWith('lead_time_max_days'),
                    TextInput::make('lead_time_max_days')->label(__('app.fields.lead_time_max_days'))
                        ->helperText(__('app.onboarding.laboratories.lead_time_max_help'))
                        ->integer()->minValue(0)->maxValue(90)->gte('lead_time_days')->suffix(__('app.onboarding.laboratories.days')),
                ])
                ->columns(4)
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel(__('app.onboarding.laboratories.add')),
        ];
    }

    public function isComplete(Company $company): bool
    {
        return $company->laboratories()->where('is_active', true)->exists();
    }

    public function summary(Company $company): string
    {
        return $company->laboratories()->where('is_active', true)->get()
            ->map(fn ($lab) => match (true) {
                $lab->lead_time_days === null => $lab->name,
                $lab->lead_time_max_days > $lab->lead_time_days => __('app.onboarding.laboratories.summary_range', ['name' => $lab->name, 'min' => $lab->lead_time_days, 'max' => $lab->lead_time_max_days]),
                $lab->lead_time_days === 0 => __('app.onboarding.laboratories.summary_same_day', ['name' => $lab->name]),
                default => __('app.onboarding.laboratories.summary_item', ['name' => $lab->name, 'days' => $lab->lead_time_days]),
            })
            ->join(', ');
    }
}
