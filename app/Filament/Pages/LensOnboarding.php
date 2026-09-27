<?php

namespace App\Filament\Pages;

use App\Actions\CompleteLensOnboarding;
use App\Models\Company;
use App\Support\ReferenceLensCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class LensOnboarding extends Page
{
    protected string $view = 'filament.pages.lens-onboarding';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getDefaultState());
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultState(): array
    {
        $keys = array_keys(ReferenceLensCatalog::combinations());
        $pricing = [];

        foreach (ReferenceLensCatalog::combinations() as $key => $combination) {
            $pricing[$key] = [
                'cost' => $combination['cost'],
                'price' => $combination['price'],
                'installation_price' => $combination['installation_price'],
            ];
        }

        return ['selected_combo_keys' => $keys, 'pricing' => $pricing];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    Step::make('select')
                        ->label(__('app.lens_onboarding.step_select'))
                        ->description(__('app.lens_onboarding.step_select_description'))
                        ->schema([
                            CheckboxList::make('selected_combo_keys')
                                ->hiddenLabel()
                                ->live()
                                ->required()
                                ->columns(2)
                                ->options(fn () => array_map(
                                    fn (array $combination): string => "{$combination['type']} · {$combination['technology']} · {$combination['material']}",
                                    ReferenceLensCatalog::combinations(),
                                )),
                        ]),
                    Step::make('pricing')
                        ->label(__('app.lens_onboarding.step_pricing'))
                        ->description(__('app.lens_onboarding.step_pricing_description'))
                        ->schema($this->getPricingSections()),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit">
                            {{ __('app.lens_onboarding.submit') }}
                        </x-filament::button>
                        BLADE))),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    private function getPricingSections(): array
    {
        $sections = [];

        foreach (ReferenceLensCatalog::combinations() as $key => $combination) {
            $sections[] = Section::make("{$combination['type']} · {$combination['technology']} · {$combination['material']}")
                ->visible(fn (Get $get): bool => in_array($key, $get('selected_combo_keys') ?? [], true))
                ->columns(3)
                ->schema([
                    TextInput::make("pricing.{$key}.cost")
                        ->label(__('app.fields.cost'))
                        ->required()
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make("pricing.{$key}.price")
                        ->label(__('app.fields.price'))
                        ->required()
                        ->numeric()
                        ->prefix('$'),
                    TextInput::make("pricing.{$key}.installation_price")
                        ->label(__('app.fields.installation_price'))
                        ->required()
                        ->numeric()
                        ->prefix('$'),
                ]);
        }

        return $sections;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('skip')
                ->label(__('app.lens_onboarding.skip'))
                ->color('gray')
                ->action('skip'),
        ];
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        $overrides = [];
        foreach ($state['selected_combo_keys'] as $key) {
            $overrides[$key] = $state['pricing'][$key];
        }

        app(CompleteLensOnboarding::class)->handle(Company::current(), $state['selected_combo_keys'], $overrides);

        Notification::make()->success()->title(__('app.lens_onboarding.completed_notification'))->send();

        $this->redirect(Dashboard::getUrl());
    }

    public function skip(): void
    {
        app(CompleteLensOnboarding::class)->handle(Company::current(), []);

        Notification::make()->title(__('app.lens_onboarding.skipped_notification'))->send();

        $this->redirect(Dashboard::getUrl());
    }
}
