<?php

namespace App\Filament\Pages\Onboarding\Steps;

use App\Actions\CreateReferenceLensCombinations;
use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Models\Company;
use App\Models\LensCombination;
use App\Models\Supplier;
use App\Support\ReferenceLensCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;

class LensesStep extends OnboardingStep
{
    public static function key(): string
    {
        return 'lenses';
    }

    public function label(): string
    {
        return __('app.onboarding.lenses.label');
    }

    public function description(): string
    {
        return __('app.onboarding.lenses.description');
    }

    public function components(): array
    {
        $hasCombinations = $this->isComplete(Company::current());

        return [
            CheckboxList::make('selected_combo_keys')
                ->label(__('app.onboarding.lenses.selected_combo_keys'))
                ->helperText($hasCombinations ? __('app.onboarding.lenses.already_have') : null)
                ->live()
                ->required(! $hasCombinations)
                ->columns(2)
                ->options(array_map(
                    fn (array $c): string => "{$c['type']} · {$c['technology']} · {$c['material']}",
                    ReferenceLensCatalog::combinations(),
                )),
            ...$this->pricingSections(),
        ];
    }

    /** @return array<int, Section> */
    private function pricingSections(): array
    {
        $labs = $this->activeLabs()->pluck('name', 'id')->all();
        $sections = [];
        foreach (ReferenceLensCatalog::combinations() as $key => $c) {
            $sections[] = Section::make("{$c['type']} · {$c['technology']} · {$c['material']}")
                ->visible(fn (Get $get): bool => in_array($key, $get('selected_combo_keys') ?? [], true))
                ->columns(3)
                ->schema([
                    TextInput::make("pricing.{$key}.cost")->label(__('app.fields.cost'))->required()->numeric()->minValue(0)->prefix('$'),
                    TextInput::make("pricing.{$key}.price")->label(__('app.fields.price'))->required()->numeric()->minValue(1)->prefix('$'),
                    TextInput::make("pricing.{$key}.installation_price")->label(__('app.fields.installation_price'))->required()->numeric()->minValue(0)->prefix('$'),
                    // With a single lab there is nothing to choose: it makes every lens.
                    CheckboxList::make("pricing.{$key}.supplier_ids")
                        ->label(__('app.onboarding.lenses.laboratories'))
                        ->options($labs)
                        ->visible(count($labs) > 1)
                        ->required()
                        ->columns(3)
                        ->columnSpanFull(),
                ]);
        }

        return $sections;
    }

    public function fill(Company $company): array
    {
        $catalog = ReferenceLensCatalog::combinations();
        $labIds = $this->activeLabs()->pluck('id')->all();

        return [
            'selected_combo_keys' => $this->isComplete($company) ? [] : array_keys($catalog),
            'pricing' => array_map(fn (array $c) => [
                'cost' => $c['cost'], 'price' => $c['price'], 'installation_price' => $c['installation_price'],
                'supplier_ids' => $labIds,
            ], $catalog),
        ];
    }

    public function save(Company $company, array $state): void
    {
        $keys = $state['selected_combo_keys'] ?? [];
        $overrides = array_intersect_key($state['pricing'] ?? [], array_flip($keys));

        app(CreateReferenceLensCombinations::class)->handle($company, $keys, $overrides);
    }

    /** @return Collection<int, Supplier> */
    private function activeLabs(): Collection
    {
        return Supplier::query()->laboratories()->where('is_active', true)->orderBy('id')->get();
    }

    public function isComplete(Company $company): bool
    {
        return LensCombination::query()->sellable()->exists();
    }

    public function summary(Company $company): string
    {
        return __('app.onboarding.lenses.summary', ['count' => LensCombination::query()->where('is_active', true)->count()]);
    }
}
