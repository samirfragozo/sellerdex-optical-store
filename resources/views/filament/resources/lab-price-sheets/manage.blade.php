@php
    $invalid = fn (string $path): string => $errors->has($path) ? 'fi-invalid' : '';
    $headerCell = 'bg-white p-2 align-top dark:bg-gray-900';
@endphp

<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex min-w-64 flex-col gap-1 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('app.fields.name') }}
                <div class="fi-input-wrp {{ $invalid('name') }}">
                    <input type="text" wire:model="name" maxlength="255" required class="fi-input" />
                </div>
            </label>
            <x-filament::button color="gray" icon="heroicon-m-plus" wire:click="addColumn">
                {{ __('app.lab_sheets.add_column') }}
            </x-filament::button>
            <x-filament::button color="gray" icon="heroicon-m-plus" wire:click="addRow">
                {{ __('app.lab_sheets.add_row') }}
            </x-filament::button>
        </div>

        @error('name')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
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
                                <th scope="col" wire:key="column-{{ $column['key'] }}" class="{{ $headerCell }} sticky top-0 z-10 min-w-44 border-b border-gray-950/5 font-normal dark:border-white/10">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1">
                                            <div class="fi-input-wrp grow {{ $invalid("columns.{$colIndex}.label") }}">
                                                <input type="text" wire:model="columns.{{ $colIndex }}.label" maxlength="100"
                                                    title="{{ __('app.lab_sheets.column_help') }}" aria-label="{{ __('app.lab_sheets.column') }}"
                                                    placeholder="{{ __('app.lab_sheets.column') }}" class="fi-input font-medium" />
                                            </div>
                                            <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" size="sm"
                                                :label="__('app.lab_sheets.remove_column')"
                                                wire:click="removeColumn('{{ $column['key'] }}')"
                                                wire:confirm="{{ __('app.lab_sheets.remove_column_confirm') }}" />
                                        </div>
                                        <div class="fi-input-wrp {{ $invalid("columns.{$colIndex}.lens_type_id") }}">
                                            <select wire:model="columns.{{ $colIndex }}.lens_type_id" aria-label="{{ __('app.resources.lens_type.label') }}" class="fi-select-input">
                                                <option value="">{{ __('app.resources.lens_type.label') }}</option>
                                                @foreach ($lensTypes as $id => $typeName)
                                                    <option value="{{ $id }}">{{ $typeName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="fi-input-wrp {{ $invalid("columns.{$colIndex}.lens_technology_id") }}">
                                            <select wire:model="columns.{{ $colIndex }}.lens_technology_id" aria-label="{{ __('app.resources.lens_technology.label') }}" class="fi-select-input">
                                                <option value="">{{ __('app.resources.lens_technology.label') }}</option>
                                                @foreach ($lensTechnologies as $id => $technologyName)
                                                    <option value="{{ $id }}">{{ $technologyName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex gap-1">
                                            @foreach (['add_min', 'add_max'] as $field)
                                                <div class="fi-input-wrp {{ $invalid("columns.{$colIndex}.{$field}") }}">
                                                    <input type="text" inputmode="decimal" wire:model="columns.{{ $colIndex }}.{{ $field }}"
                                                        aria-label="{{ __("app.fields.{$field}") }}" title="{{ __("app.fields.{$field}") }}"
                                                        placeholder="{{ __("app.fields.{$field}") }}" class="fi-input w-full" />
                                                </div>
                                            @endforeach
                                        </div>
                                        @foreach ($errors->get("columns.{$colIndex}.*") as $messages)
                                            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $messages[0] }}</p>
                                        @endforeach
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $rowIndex => $row)
                            <tr wire:key="row-{{ $row['key'] }}">
                                <th scope="row" class="{{ $headerCell }} sticky left-0 z-10 min-w-64 border-e border-gray-950/5 font-normal dark:border-white/10">
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1">
                                            <div class="fi-input-wrp grow {{ $invalid("rows.{$rowIndex}.label") }}">
                                                <input type="text" wire:model="rows.{{ $rowIndex }}.label" maxlength="100"
                                                    title="{{ __('app.lab_sheets.row_help') }}" aria-label="{{ __('app.lab_sheets.row') }}"
                                                    placeholder="{{ __('app.lab_sheets.row') }}" class="fi-input font-medium" />
                                            </div>
                                            <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" size="sm"
                                                :label="__('app.lab_sheets.remove_row')"
                                                wire:click="removeRow('{{ $row['key'] }}')"
                                                wire:confirm="{{ __('app.lab_sheets.remove_row_confirm') }}" />
                                        </div>
                                        <div class="fi-input-wrp {{ $invalid("rows.{$rowIndex}.lens_material_id") }}">
                                            <select wire:model="rows.{{ $rowIndex }}.lens_material_id" aria-label="{{ __('app.resources.lens_material.label') }}" class="fi-select-input">
                                                <option value="">{{ __('app.resources.lens_material.label') }}</option>
                                                @foreach ($lensMaterials as $id => $materialName)
                                                    <option value="{{ $id }}">{{ $materialName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @foreach ([['sphere_min', 'sphere_max'], ['cylinder_min', 'cylinder_max']] as $range)
                                            <div class="flex gap-1">
                                                @foreach ($range as $field)
                                                    <div class="fi-input-wrp {{ $invalid("rows.{$rowIndex}.{$field}") }}">
                                                        <input type="text" inputmode="decimal" wire:model="rows.{{ $rowIndex }}.{{ $field }}"
                                                            aria-label="{{ __("app.fields.{$field}") }}" title="{{ __("app.fields.{$field}") }}"
                                                            placeholder="{{ __("app.fields.{$field}") }}" class="fi-input w-full" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                        @foreach ($errors->get("rows.{$rowIndex}.*") as $messages)
                                            <p class="text-xs text-danger-600 dark:text-danger-400">{{ $messages[0] }}</p>
                                        @endforeach
                                    </div>
                                </th>
                                @foreach ($columns as $colIndex => $column)
                                    @php($costPath = "costs.{$row['key']}.{$column['key']}")
                                    <td wire:key="cost-{{ $row['key'] }}-{{ $column['key'] }}" class="border-b border-gray-950/5 p-2 align-top dark:border-white/10">
                                        <div class="fi-input-wrp {{ $invalid($costPath) }}" title="{{ $errors->first($costPath) }}">
                                            <input type="text" inputmode="numeric" wire:model="{{ $costPath }}"
                                                data-row="{{ $rowIndex }}" data-col="{{ $colIndex }}"
                                                aria-label="{{ __('app.fields.cost') }}: {{ $row['label'] }} / {{ $column['label'] }}"
                                                @error($costPath) aria-invalid="true" @enderror
                                                class="fi-input w-28 text-end tabular-nums" />
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
