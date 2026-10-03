<x-filament-panels::page>
    @if ($this->products()->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.inventory.no_products_to_count') }}</p>
    @else
        <form wire:submit="save" class="flex flex-col gap-4">
            {{ $this->form }}
            <div class="flex justify-end">
                <x-filament::button type="submit">{{ __('app.inventory.save_count') }}</x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
