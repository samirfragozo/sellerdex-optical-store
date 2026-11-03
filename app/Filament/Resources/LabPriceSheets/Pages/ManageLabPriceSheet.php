<?php

namespace App\Filament\Resources\LabPriceSheets\Pages;

use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Company;
use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\Prescription;
use App\Support\LabSheetPaste;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * The lab's price sheet as one spreadsheet-like grid: columns (designs) across,
 * rows (material + sphere/cylinder range) down, one cost per cell. Nothing is
 * stored until "save"; a blank cell means the lab does not sell that pair.
 *
 * @property LabPriceSheet $record
 */
class ManageLabPriceSheet extends Page
{
    use InteractsWithRecord;

    protected static string $resource = LabPriceSheetResource::class;

    protected string $view = 'filament.resources.lab-price-sheets.manage';

    public string $name = '';

    /** @var list<array{key: string, id: ?int, label: string, lens_type_id: ?int, lens_technology_id: ?int, add_min: ?string, add_max: ?string}> */
    public array $columns = [];

    /** @var list<array{key: string, id: ?int, label: string, lens_material_id: ?int, sphere_min: string, sphere_max: string, cylinder_min: string, cylinder_max: string}> */
    public array $rows = [];

    /** @var array<string, array<string, ?string>> cost per [rowKey][columnKey] */
    public array $costs = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->loadSheet();
    }

    /** @param  array<string, mixed>  $parameters */
    public static function canAccess(array $parameters = []): bool
    {
        return isset($parameters['record']) && static::getResource()::canEdit($parameters['record']);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        $labUrl = fn (): string => SupplierResource::getUrl('edit', ['record' => $this->getRecord()->supplier_id]);

        return [
            Action::make('back_to_lab')->label(__('app.lab_sheets.back_to_lab'))->color('gray')->url($labUrl),
            DeleteAction::make()->successRedirectUrl($labUrl),
        ];
    }

    /** @return array{lensTypes: array<int, string>, lensTechnologies: array<int, string>, lensMaterials: array<int, string>} */
    protected function getViewData(): array
    {
        return [
            'lensTypes' => LensType::query()->orderBy('name')->pluck('name', 'id')->all(),
            'lensTechnologies' => LensTechnology::query()->orderBy('name')->pluck('name', 'id')->all(),
            'lensMaterials' => LensMaterial::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    public function addColumn(): void
    {
        $key = 'new-'.Str::uuid();
        $this->columns[] = ['key' => $key, 'id' => null, 'label' => '', 'lens_type_id' => null, 'lens_technology_id' => null, 'add_min' => null, 'add_max' => null];

        foreach (array_keys($this->costs) as $rowKey) {
            $this->costs[$rowKey][$key] = null;
        }
    }

    public function addRow(): void
    {
        $key = 'new-'.Str::uuid();
        [$sphereMin, $sphereMax] = Prescription::LIMITS['sphere'];
        [$cylinderMin, $cylinderMax] = Prescription::LIMITS['cylinder'];
        $this->rows[] = [
            'key' => $key, 'id' => null, 'label' => '', 'lens_material_id' => null,
            'sphere_min' => (string) $sphereMin, 'sphere_max' => (string) $sphereMax,
            'cylinder_min' => (string) $cylinderMin, 'cylinder_max' => (string) $cylinderMax,
        ];
        $this->costs[$key] = array_fill_keys(array_column($this->columns, 'key'), null);
    }

    public function removeColumn(string $key): void
    {
        $this->columns = array_values(array_filter($this->columns, fn (array $column): bool => $column['key'] !== $key));

        foreach (array_keys($this->costs) as $rowKey) {
            unset($this->costs[$rowKey][$key]);
        }
    }

    public function removeRow(string $key): void
    {
        $this->rows = array_values(array_filter($this->rows, fn (array $row): bool => $row['key'] !== $key));
        unset($this->costs[$key]);
    }

    /**
     * Write a block copied from a spreadsheet into the costs, starting at the
     * given cell and moving right through the columns and down through the rows.
     */
    public function paste(string $rowKey, string $columnKey, string $text): void
    {
        $rowKeys = array_column($this->rows, 'key');
        $columnKeys = array_column($this->columns, 'key');
        $firstRow = array_search($rowKey, $rowKeys, true);
        $firstColumn = array_search($columnKey, $columnKeys, true);

        if ($firstRow === false || $firstColumn === false) {
            return;
        }

        $pasted = 0;
        $skipped = 0;

        foreach (LabSheetPaste::parse($text) as $rowOffset => $values) {
            foreach ($values as $columnOffset => $value) {
                $targetRow = $rowKeys[$firstRow + $rowOffset] ?? null;
                $targetColumn = $columnKeys[$firstColumn + $columnOffset] ?? null;

                if ($targetRow === null || $targetColumn === null) {
                    $skipped++;

                    continue;
                }

                $this->costs[$targetRow][$targetColumn] = $value === null ? null : (string) $value;
                $pasted++;
            }
        }

        $notification = Notification::make()->title(__('app.lab_sheets.pasted', ['count' => $pasted]));

        if ($skipped > 0) {
            $notification->warning()->body(__('app.lab_sheets.paste_skipped', ['count' => $skipped]));
        } else {
            $notification->success();
        }

        $notification->send();
    }

    public function save(): void
    {
        try {
            $this->validate($this->gridRules(), [], $this->gridAttributes());
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('app.lab_sheets.invalid', ['count' => count($exception->errors())]))->send();
            $this->dispatch('lab-sheet-invalid');

            throw $exception;
        }

        $sheet = $this->getRecord();

        DB::transaction(function () use ($sheet): void {
            $sheet->update(['name' => $this->name]);

            $columnIds = [];
            foreach ($this->columns as $position => $column) {
                $model = $sheet->columns()->findOrNew($column['id']);
                $model->fill([
                    'label' => $column['label'],
                    'lens_type_id' => $column['lens_type_id'],
                    'lens_technology_id' => $column['lens_technology_id'],
                    'add_min' => filled($column['add_min']) ? $column['add_min'] : null,
                    'add_max' => filled($column['add_max']) ? $column['add_max'] : null,
                    'sort_order' => $position,
                ])->save();
                $columnIds[$column['key']] = $model->id;
            }

            $rowIds = [];
            foreach ($this->rows as $position => $row) {
                $model = $sheet->rows()->findOrNew($row['id']);
                $model->fill([
                    'label' => $row['label'],
                    'lens_material_id' => $row['lens_material_id'],
                    'sphere_min' => $row['sphere_min'],
                    'sphere_max' => $row['sphere_max'],
                    'cylinder_min' => $row['cylinder_min'],
                    'cylinder_max' => $row['cylinder_max'],
                    'sort_order' => $position,
                ])->save();
                $rowIds[$row['key']] = $model->id;
            }

            // Their cells go with them (cascade).
            $sheet->columns()->whereNotIn('id', $columnIds)->delete();
            $sheet->rows()->whereNotIn('id', $rowIds)->delete();

            $existing = $sheet->cells()->get()->keyBy(fn (LabSheetCell $cell): string => "{$cell->lab_sheet_row_id}-{$cell->lab_sheet_column_id}");

            foreach ($rowIds as $rowKey => $rowId) {
                foreach ($columnIds as $columnKey => $columnId) {
                    $cost = $this->costs[$rowKey][$columnKey] ?? null;
                    $cell = $existing->get("{$rowId}-{$columnId}");

                    if (blank($cost)) {
                        $cell?->delete();
                    } elseif ($cell === null) {
                        LabSheetCell::create(['lab_sheet_row_id' => $rowId, 'lab_sheet_column_id' => $columnId, 'cost' => (int) $cost]);
                    } elseif ($cell->cost !== (int) $cost) {
                        $cell->update(['cost' => (int) $cost]);
                    }
                }
            }
        });

        $this->loadSheet();
        $this->dispatch('lab-sheet-saved');

        Notification::make()->success()->title(__('app.lab_sheets.saved'))->send();
    }

    /** Fill the grid state from the stored sheet; existing records are keyed "c{id}" / "r{id}". */
    protected function loadSheet(): void
    {
        $sheet = $this->getRecord();
        $this->name = $sheet->name;

        $this->columns = $sheet->columns()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (LabSheetColumn $column): array => [
                'key' => "c{$column->id}", 'id' => $column->id, 'label' => $column->label,
                'lens_type_id' => $column->lens_type_id, 'lens_technology_id' => $column->lens_technology_id,
                'add_min' => $column->add_min, 'add_max' => $column->add_max,
            ])->all();

        $this->rows = $sheet->rows()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (LabSheetRow $row): array => [
                'key' => "r{$row->id}", 'id' => $row->id, 'label' => $row->label, 'lens_material_id' => $row->lens_material_id,
                'sphere_min' => $row->sphere_min, 'sphere_max' => $row->sphere_max,
                'cylinder_min' => $row->cylinder_min, 'cylinder_max' => $row->cylinder_max,
            ])->all();

        $blankRow = array_fill_keys(array_column($this->columns, 'key'), null);
        $this->costs = array_fill_keys(array_column($this->rows, 'key'), $blankRow);

        foreach ($sheet->cells()->get() as $cell) {
            $this->costs["r{$cell->lab_sheet_row_id}"]["c{$cell->lab_sheet_column_id}"] = (string) $cell->cost;
        }
    }

    /** @return array<string, array<int, mixed>> */
    protected function gridRules(): array
    {
        $ofCompany = fn (string $table): Exists => Rule::exists($table, 'id')->where('company_id', Company::current()->id);
        $diopter = fn (string $scale): array => ['required', 'numeric', 'min:'.Prescription::LIMITS[$scale][0], 'max:'.Prescription::LIMITS[$scale][1], 'multiple_of:0.25'];
        $addition = ['nullable', 'numeric', 'min:0', 'max:'.Prescription::LIMITS['add'][1], 'multiple_of:0.25'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'columns.*.id' => ['nullable', 'integer'],
            'rows.*.id' => ['nullable', 'integer'],
            'columns.*.label' => ['required', 'string', 'max:100'],
            'columns.*.lens_type_id' => ['required', 'integer', $ofCompany('lens_types')],
            'columns.*.lens_technology_id' => ['required', 'integer', $ofCompany('lens_technologies')],
            'columns.*.add_min' => $addition,
            // ponytail: gte fails against a blank add_min, so "max ≥ min only when both are set" is a closure.
            'columns.*.add_max' => [...$addition, function (string $attribute, mixed $value, Closure $fail): void {
                $min = data_get($this, Str::replaceLast('add_max', 'add_min', $attribute));

                if (filled($min) && filled($value) && (float) $value < (float) $min) {
                    $fail(__('validation.gte.numeric', ['attribute' => __('app.fields.add_max'), 'value' => $min]));
                }
            }],
            'rows.*.label' => ['required', 'string', 'max:100'],
            'rows.*.lens_material_id' => ['required', 'integer', $ofCompany('lens_materials')],
            'rows.*.sphere_min' => $diopter('sphere'),
            'rows.*.sphere_max' => [...$diopter('sphere'), 'gte:rows.*.sphere_min'],
            'rows.*.cylinder_min' => $diopter('cylinder'),
            'rows.*.cylinder_max' => [...$diopter('cylinder'), 'gte:rows.*.cylinder_min'],
            'costs.*.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    protected function gridAttributes(): array
    {
        return [
            'name' => __('app.fields.name'),
            'columns.*.label' => __('app.lab_sheets.column'),
            'columns.*.lens_type_id' => __('app.resources.lens_type.label'),
            'columns.*.lens_technology_id' => __('app.resources.lens_technology.label'),
            'columns.*.add_min' => __('app.fields.add_min'),
            'columns.*.add_max' => __('app.fields.add_max'),
            'rows.*.label' => __('app.lab_sheets.row'),
            'rows.*.lens_material_id' => __('app.resources.lens_material.label'),
            'rows.*.sphere_min' => __('app.fields.sphere_min'),
            'rows.*.sphere_max' => __('app.fields.sphere_max'),
            'rows.*.cylinder_min' => __('app.fields.cylinder_min'),
            'rows.*.cylinder_max' => __('app.fields.cylinder_max'),
            'costs.*.*' => __('app.fields.cost'),
        ];
    }
}
