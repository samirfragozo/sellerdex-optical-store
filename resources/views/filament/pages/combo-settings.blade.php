<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-4">
        {{ $this->form }}
        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('app.business.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
