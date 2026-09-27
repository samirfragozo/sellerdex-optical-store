@php($warnings = collect(rescue(fn () => \App\Models\Company::current()->saleReadiness(), [], report: false))->where('severity', \App\Enums\ReadinessSeverity::Warning))
@if ($warnings->isNotEmpty())
    <div class="flex flex-col gap-2" x-data="{ hidden: sessionStorage.getItem('readiness-banner-hidden') === '1' }" x-show="! hidden">
        @foreach ($warnings as $issue)
            <div role="status" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-warning-300 bg-warning-50 px-4 py-2 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
                <span><span aria-hidden="true">⚠</span> {{ $issue->message }}</span>
                <span class="flex gap-3">
                    <x-filament::link :href="$issue->url">{{ __('app.readiness.fix') }}</x-filament::link>
                    <x-filament::link tag="button" color="gray" x-on:click="try { sessionStorage.setItem('readiness-banner-hidden', '1') } catch (e) {}; hidden = true">{{ __('app.readiness.dismiss') }}</x-filament::link>
                </span>
            </div>
        @endforeach
    </div>
@endif
