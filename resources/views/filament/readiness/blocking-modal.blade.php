@php($blockers = collect(rescue(fn () => \App\Models\Company::current()->saleReadiness(), [], report: false))
    ->filter(fn ($i) => $i->severity === \App\Enums\ReadinessSeverity::Blocking && $i->scope === null))
@if ($blockers->isNotEmpty())
    <div x-data x-init="$nextTick(() => $dispatch('open-modal', { id: 'readiness-blocking' }))">
        <x-filament::modal id="readiness-blocking" icon="heroicon-o-exclamation-triangle" icon-color="danger" :close-by-clicking-away="false">
            <x-slot name="heading">{{ __('app.readiness.blocking_title') }}</x-slot>
            <ul class="flex flex-col gap-3">
                @foreach ($blockers as $issue)
                    <li class="flex flex-col gap-1 text-sm">
                        <span>{{ $issue->message }}</span>
                        <x-filament::link :href="$issue->url">{{ __('app.readiness.fix') }}</x-filament::link>
                    </li>
                @endforeach
            </ul>
        </x-filament::modal>
    </div>
@endif
