<x-filament-panels::page>
    @php
        $steps = $this->stepInstances();
        $company = \App\Models\Company::current();
        $reached = $this->reachedIndex();
        $currentIndex = collect($steps)->search(fn ($s) => $s::key() === $this->step);
    @endphp

    <div class="grid gap-6 md:grid-cols-[16rem_1fr]">
        <nav aria-label="{{ __('app.onboarding.steps_nav') }}" class="flex flex-col gap-1">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('app.onboarding.progress', ['current' => $currentIndex + 1, 'total' => count($steps)]) }}
            </p>
            <div class="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-1.5 rounded-full bg-primary-600" style="width: {{ round(($currentIndex + 1) / count($steps) * 100) }}%"></div>
            </div>
            <ol class="mt-3 flex flex-col gap-1">
                @foreach ($steps as $i => $s)
                    @php($isCurrent = $s::key() === $this->step)
                    <li>
                        <button type="button"
                            wire:click="goTo('{{ $s::key() }}')"
                            @disabled($i > $reached)
                            @if ($isCurrent) aria-current="step" @endif
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-50 {{ $isCurrent ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300' : 'hover:bg-gray-50 dark:hover:bg-white/5' }}">
                            <span aria-hidden="true">{{ $s->isComplete($company) && ! $isCurrent ? '✓' : ($isCurrent ? '●' : '○') }}</span>
                            <span>{{ $i + 1 }}. {{ $s->label() }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>

        <section class="flex flex-col gap-4">
            <header>
                <h2 class="text-lg font-semibold">{{ $this->currentStep()->label() }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->currentStep()->description() }}</p>
            </header>

            @if ($this->step === \App\Filament\Pages\Onboarding\Steps\SummaryStep::key())
                <ul class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($steps as $s)
                        @continue($s::key() === $this->step)
                        <li class="flex items-center justify-between gap-4 py-2 text-sm">
                            <span><strong>{{ $s->label() }}</strong> · {{ $s->summary($company) }}</span>
                            <x-filament::link tag="button" wire:click="goTo('{{ $s::key() }}')">{{ __('app.onboarding.edit') }}</x-filament::link>
                        </li>
                    @endforeach
                </ul>

                @foreach (collect($company->saleReadiness())->where('severity', \App\Enums\ReadinessSeverity::Blocking) as $issue)
                    <p class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ $issue->message }}</p>
                @endforeach

                <div class="flex justify-between gap-3">
                    <x-filament::button color="gray" wire:click="previous">{{ __('app.onboarding.back') }}</x-filament::button>
                    <x-filament::button wire:click="finish">{{ __('app.onboarding.go_sell') }}</x-filament::button>
                </div>
            @else
                <form wire:submit="next" class="flex flex-col gap-4">
                    {{ $this->form }}
                    <div class="flex justify-between gap-3">
                        <x-filament::button color="gray" wire:click="previous" :disabled="$currentIndex === 0">{{ __('app.onboarding.back') }}</x-filament::button>
                        <x-filament::button type="submit">{{ __('app.onboarding.continue') }}</x-filament::button>
                    </div>
                </form>
            @endif
        </section>
    </div>
</x-filament-panels::page>
