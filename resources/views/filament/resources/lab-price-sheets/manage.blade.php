@php
    $invalid = fn (string $path): string => $errors->has($path) ? 'fi-invalid' : '';
    $headerCell = 'bg-white p-1 align-top dark:bg-gray-900';
    $errorId = fn (string $path): string => 'error-'.str_replace('.', '-', $path);
    // ponytail: raw attribute string so each input/select ties itself to its message in one call.
    $describedBy = fn (string $path): string => $errors->has($path) ? 'aria-invalid="true" aria-describedby="'.e($errorId($path)).'"' : '';
    // Collapsed ranges keep a row ~44px tall; a range with an error opens itself.
    $openOnError = fn (array $paths): string => $errors->hasAny($paths) ? 'open' : '';
    $summaryClass = 'cursor-pointer rounded px-1 text-start text-xs leading-4 text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-primary-600 dark:text-gray-400 dark:hover:text-gray-200';
    $diopter = fn (mixed $value): string => filled($value) ? rtrim(rtrim(\App\Models\Prescription::formatDiopter($value), '0'), '.') : '…';
    $addition = fn (mixed $value): string => filled($value) ? number_format((float) $value, 2) : '…';
@endphp

<x-filament-panels::page>
    {{-- Paste, Enter and the unsaved-changes guard are delegated here so each cost cell stays a plain input. --}}
    <form wire:submit="save" class="flex flex-col gap-4"
        x-data="{
            dirty: false,
            onEnter(event) {
                const input = event.target;
                if (input.tagName !== 'INPUT') return;
                event.preventDefault();
                if (input.dataset.row === undefined) return;
                this.$root.querySelector(`[data-col='${input.dataset.col}'][data-row='${Number(input.dataset.row) + 1}']`)?.focus();
            },
            onPaste(event) {
                const input = event.target;
                const text = event.clipboardData?.getData('text') ?? '';
                if (input.dataset.rowKey === undefined || ! /[\t\n\r]/.test(text)) return;
                event.preventDefault();
                this.dirty = true;
                this.$wire.paste(input.dataset.rowKey, input.dataset.colKey, text);
            },
            focusFirstInvalid() {
                const invalid = this.$root.querySelector('[aria-invalid=true]');
                invalid?.scrollIntoView({ block: 'center' });
                invalid?.focus({ preventScroll: true });
            },
        }"
        x-on:input="dirty = true"
        x-on:keydown.enter="onEnter($event)"
        x-on:paste="onPaste($event)"
        x-on:lab-sheet-saved.window="dirty = false"
        x-on:lab-sheet-invalid.window="focusFirstInvalid()"
        x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
        x-on:livewire:navigate.window="if (dirty && ! confirm(@js(__('app.lab_sheets.unsaved_warning')))) { $event.preventDefault(); }">
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex min-w-64 flex-col gap-1 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('app.fields.name') }}
                <div class="fi-input-wrp {{ $invalid('name') }}">
                    <input type="text" wire:model="name" maxlength="255" required class="fi-input" {!! $describedBy('name') !!} />
                </div>
            </label>
            <x-filament::button color="gray" icon="heroicon-m-plus" wire:click="addColumn" x-on:click="dirty = true">
                {{ __('app.lab_sheets.add_column') }}
            </x-filament::button>
            <x-filament::button color="gray" icon="heroicon-m-plus" wire:click="addRow" x-on:click="dirty = true">
                {{ __('app.lab_sheets.add_row') }}
            </x-filament::button>
        </div>

        @error('name')
            <p id="{{ $errorId('name') }}" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror

        @if (empty($columns) && empty($rows))
            <p class="rounded-xl p-6 text-center text-sm text-gray-500 ring-1 ring-gray-950/5 dark:text-gray-400 dark:ring-white/10">
                {{ __('app.lab_sheets.empty') }}
            </p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.lab_sheets.grid_help') }}</p>

            {{-- ponytail: 70vh cap so the header row can stick while the sheet scrolls inside the box. --}}
            <div class="max-h-[70vh] overflow-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="border-separate border-spacing-0 text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="{{ $headerCell }} sticky top-0 left-0 z-20 text-start text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('app.lab_sheets.rows') }}
                            </th>
                            @foreach ($columns as $colIndex => $column)
                                @php($addPaths = ["columns.{$colIndex}.add_min", "columns.{$colIndex}.add_max"])
                                <th scope="col" wire:key="column-{{ $column['key'] }}" class="{{ $headerCell }} sticky top-0 z-10 min-w-56 border-b border-gray-950/5 font-normal dark:border-white/10">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1">
                                            <div class="fi-input-wrp grow {{ $invalid("columns.{$colIndex}.label") }}">
                                                <input type="text" wire:model="columns.{{ $colIndex }}.label" maxlength="100"
                                                    title="{{ __('app.lab_sheets.column_help') }}" aria-label="{{ __('app.lab_sheets.column') }}"
                                                    placeholder="{{ __('app.lab_sheets.column') }}" class="fi-input font-medium"
                                                    {!! $describedBy("columns.{$colIndex}.label") !!} />
                                            </div>
                                            <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" size="sm"
                                                :label="__('app.lab_sheets.remove_column')"
                                                wire:click="removeColumn('{{ $column['key'] }}')" x-on:click="dirty = true"
                                                wire:confirm="{{ __('app.lab_sheets.remove_column_confirm') }}" />
                                        </div>
                                        <div class="flex gap-1">
                                            <div class="fi-input-wrp min-w-0 flex-1 {{ $invalid("columns.{$colIndex}.lens_type_id") }}">
                                                <select wire:model="columns.{{ $colIndex }}.lens_type_id" aria-label="{{ __('app.resources.lens_type.label') }}" class="fi-select-input"
                                                    {!! $describedBy("columns.{$colIndex}.lens_type_id") !!}>
                                                    <option value="">{{ __('app.resources.lens_type.label') }}</option>
                                                    @foreach ($lensTypes as $id => $typeName)
                                                        <option value="{{ $id }}">{{ $typeName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="fi-input-wrp min-w-0 flex-1 {{ $invalid("columns.{$colIndex}.lens_technology_id") }}">
                                                <select wire:model="columns.{{ $colIndex }}.lens_technology_id" aria-label="{{ __('app.resources.lens_technology.label') }}" class="fi-select-input"
                                                    {!! $describedBy("columns.{$colIndex}.lens_technology_id") !!}>
                                                    <option value="">{{ __('app.resources.lens_technology.label') }}</option>
                                                    @foreach ($lensTechnologies as $id => $technologyName)
                                                        <option value="{{ $id }}">{{ $technologyName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <details {{ $openOnError($addPaths) }}>
                                            <summary class="{{ $summaryClass }}">
                                                {{ blank($column['add_min']) && blank($column['add_max'])
                                                    ? __('app.lab_sheets.add_open')
                                                    : __('app.lab_sheets.add_summary', ['min' => $addition($column['add_min']), 'max' => $addition($column['add_max'])]) }}
                                            </summary>
                                            <div class="flex gap-1 pt-1">
                                                @foreach (['add_min', 'add_max'] as $field)
                                                    <div class="fi-input-wrp {{ $invalid("columns.{$colIndex}.{$field}") }}">
                                                        <input type="text" inputmode="decimal" wire:model="columns.{{ $colIndex }}.{{ $field }}"
                                                            aria-label="{{ __("app.fields.{$field}") }}" title="{{ __("app.fields.{$field}") }}"
                                                            placeholder="{{ __("app.fields.{$field}") }}" class="fi-input w-full"
                                                            {!! $describedBy("columns.{$colIndex}.{$field}") !!} />
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                        @foreach ($errors->get("columns.{$colIndex}.*") as $path => $messages)
                                            <p id="{{ $errorId($path) }}" class="text-xs text-danger-600 dark:text-danger-400">{{ $messages[0] }}</p>
                                        @endforeach
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $rowIndex => $row)
                            @php($rangePaths = array_map(fn (string $field): string => "rows.{$rowIndex}.{$field}", ['sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max']))
                            <tr wire:key="row-{{ $row['key'] }}">
                                <th scope="row" class="{{ $headerCell }} sticky left-0 z-10 border-e border-gray-950/5 font-normal dark:border-white/10">
                                    <div class="flex w-max flex-wrap items-center gap-1">
                                        <div class="fi-input-wrp w-32 {{ $invalid("rows.{$rowIndex}.label") }}">
                                            <input type="text" wire:model="rows.{{ $rowIndex }}.label" maxlength="100"
                                                title="{{ __('app.lab_sheets.row_help') }}" aria-label="{{ __('app.lab_sheets.row') }}"
                                                placeholder="{{ __('app.lab_sheets.row') }}" class="fi-input font-medium"
                                                {!! $describedBy("rows.{$rowIndex}.label") !!} />
                                        </div>
                                        <div class="fi-input-wrp w-32 {{ $invalid("rows.{$rowIndex}.lens_material_id") }}">
                                            <select wire:model="rows.{{ $rowIndex }}.lens_material_id" aria-label="{{ __('app.resources.lens_material.label') }}" class="fi-select-input"
                                                {!! $describedBy("rows.{$rowIndex}.lens_material_id") !!}>
                                                <option value="">{{ __('app.resources.lens_material.label') }}</option>
                                                @foreach ($lensMaterials as $id => $materialName)
                                                    <option value="{{ $id }}">{{ $materialName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" size="sm"
                                            :label="__('app.lab_sheets.remove_row')"
                                            wire:click="removeRow('{{ $row['key'] }}')" x-on:click="dirty = true"
                                            wire:confirm="{{ __('app.lab_sheets.remove_row_confirm') }}" />
                                        {{-- Opening the range wraps it onto its own line under the row's label. --}}
                                        <details class="open:basis-full" {{ $openOnError($rangePaths) }}>
                                            <summary class="{{ $summaryClass }} w-28">
                                                {{ __('app.lab_sheets.range_summary', [
                                                    'sphere_min' => $diopter($row['sphere_min']), 'sphere_max' => $diopter($row['sphere_max']),
                                                    'cylinder_min' => $diopter($row['cylinder_min']), 'cylinder_max' => $diopter($row['cylinder_max']),
                                                ]) }}
                                            </summary>
                                            <div class="grid grid-cols-2 gap-1 pt-1">
                                                @foreach (['sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max'] as $field)
                                                    <div class="fi-input-wrp {{ $invalid("rows.{$rowIndex}.{$field}") }}">
                                                        <input type="text" inputmode="decimal" wire:model="rows.{{ $rowIndex }}.{{ $field }}"
                                                            aria-label="{{ __("app.fields.{$field}") }}" title="{{ __("app.fields.{$field}") }}"
                                                            placeholder="{{ __("app.fields.{$field}") }}" class="fi-input w-full"
                                                            {!! $describedBy("rows.{$rowIndex}.{$field}") !!} />
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    </div>
                                    @foreach ($errors->get("rows.{$rowIndex}.*") as $path => $messages)
                                        <p id="{{ $errorId($path) }}" class="max-w-96 text-xs text-danger-600 dark:text-danger-400">{{ $messages[0] }}</p>
                                    @endforeach
                                </th>
                                @foreach ($columns as $colIndex => $column)
                                    @php($costPath = "costs.{$row['key']}.{$column['key']}")
                                    <td wire:key="cost-{{ $row['key'] }}-{{ $column['key'] }}" class="border-b border-gray-950/5 p-1 align-top dark:border-white/10">
                                        <div class="flex items-center gap-1">
                                            <div class="fi-input-wrp {{ $invalid($costPath) }}">
                                                <input type="text" inputmode="numeric" wire:model="{{ $costPath }}"
                                                    data-row="{{ $rowIndex }}" data-col="{{ $colIndex }}"
                                                    data-row-key="{{ $row['key'] }}" data-col-key="{{ $column['key'] }}"
                                                    aria-label="{{ __('app.fields.cost') }}: {{ $row['label'] }} / {{ $column['label'] }}"
                                                    {!! $describedBy($costPath) !!}
                                                    class="fi-input w-28 text-end tabular-nums" />
                                            </div>
                                            @error($costPath)
                                                <span title="{{ $message }}" class="text-danger-600 dark:text-danger-400">
                                                    <x-filament::icon icon="heroicon-m-exclamation-circle" class="size-5" />
                                                    <span id="{{ $errorId($costPath) }}" class="sr-only">{{ $message }}</span>
                                                </span>
                                            @enderror
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex justify-end">
            <x-filament::button type="submit">{{ __('app.lab_sheets.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
