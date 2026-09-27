<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Onboarding\OnboardingStep;
use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Filament\Pages\Onboarding\Steps\LaboratoriesStep;
use App\Filament\Pages\Onboarding\Steps\LensesStep;
use App\Filament\Pages\Onboarding\Steps\PaymentMethodsStep;
use App\Filament\Pages\Onboarding\Steps\SummaryStep;
use App\Models\Company;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;

class Onboarding extends Page
{
    protected string $view = 'filament.pages.onboarding';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'onboarding';

    #[Locked]
    public string $step = '';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * Ordered steps. Later deliveries insert theirs here.
     *
     * @return array<int, class-string<OnboardingStep>>
     */
    public static function steps(): array
    {
        return [
            CompanyStep::class,
            PaymentMethodsStep::class,
            LaboratoriesStep::class,
            LensesStep::class,
            SummaryStep::class,
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->company_id !== null && $user->isAdmin();
    }

    public function getTitle(): string
    {
        return __('app.onboarding.title');
    }

    public function mount(): void
    {
        $saved = Company::current()->onboarding_step;
        $this->step = in_array($saved, $this->keys(), true) ? $saved : CompanyStep::key();
        $this->fillCurrentStep();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(Company::current())
            ->components($this->currentStep()->components());
    }

    public function next(): void
    {
        $company = Company::current();
        // ponytail: getState() also saves the schema's relationships (Filament v4), no explicit saveRelationships().
        $this->currentStep()->save($company, $this->form->getState());

        $nextKey = $this->keys()[$this->index($this->step) + 1] ?? SummaryStep::key();
        if ($this->index($nextKey) > $this->index($company->onboarding_step ?? CompanyStep::key())) {
            $company->update(['onboarding_step' => $nextKey]);
        }

        $this->moveTo($nextKey);
    }

    public function previous(): void
    {
        $this->moveTo($this->keys()[max(0, $this->index($this->step) - 1)]);
    }

    /** Only steps already reached can be revisited. */
    public function goTo(string $key): void
    {
        if (in_array($key, $this->keys(), true) && $this->index($key) <= $this->reachedIndex()) {
            $this->moveTo($key);
        }
    }

    public function finish(): void
    {
        $company = Company::current();

        if (! $company->isReadyToSell()) {
            Notification::make()->danger()->title(__('app.readiness.blocking_title'))->send();

            return;
        }

        $company->update(['onboarded_at' => now()]);
        $this->redirect(route('pos.index'));
    }

    public function currentStep(): OnboardingStep
    {
        return $this->stepFor($this->step);
    }

    /** @return array<int, OnboardingStep> */
    public function stepInstances(): array
    {
        return array_map(fn (string $class) => app($class), static::steps());
    }

    public function reachedIndex(): int
    {
        return $this->index(Company::current()->onboarding_step ?? CompanyStep::key());
    }

    /** @return array<int, string> */
    private function keys(): array
    {
        return array_map(fn (string $class) => $class::key(), static::steps());
    }

    private function index(string $key): int
    {
        return (int) array_search($key, $this->keys(), true);
    }

    private function stepFor(string $key): OnboardingStep
    {
        return app(static::steps()[$this->index($key)]);
    }

    private function moveTo(string $key): void
    {
        $this->step = $key;
        $this->cacheSchema('form');
        $this->fillCurrentStep();
    }

    private function fillCurrentStep(): void
    {
        $this->form->fill($this->currentStep()->fill(Company::current()));
    }
}
