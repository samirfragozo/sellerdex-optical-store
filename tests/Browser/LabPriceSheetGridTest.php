<?php

use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('moves down with Enter, never saves on Enter, and saves only with the button', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $sheet = LabPriceSheet::factory()->create(['name' => 'Lista 2026']);
    $column = LabSheetColumn::factory()->for($sheet, 'sheet')->create();
    $first = LabSheetRow::factory()->for($sheet, 'sheet')->create(['sort_order' => 0]);
    $second = LabSheetRow::factory()->for($sheet, 'sheet')->create(['sort_order' => 1]);

    $page = visit("/admin/lab-price-sheets/{$sheet->id}/edit")
        // Enter in a header input must not submit the form.
        ->type('input[wire\\:model="name"]', 'Lista nueva')
        ->keys('input[wire\\:model="name"]', 'Enter')
        ->wait(1)
        ->assertDontSee(__('app.lab_sheets.saved'));

    expect($sheet->fresh()->name)->toBe('Lista 2026');

    // A failed save says so and lands on the first invalid cell.
    $page->type('[data-row="0"][data-col="0"]', 'abc')
        ->click('button:has-text("'.__('app.lab_sheets.save').'")')
        ->assertSee(__('app.lab_sheets.invalid', ['count' => 1]))
        ->assertScript('document.activeElement.dataset.row', '0');

    $page->type('[data-row="0"][data-col="0"]', '74000')
        ->keys('[data-row="0"][data-col="0"]', 'Enter')
        ->assertScript('document.activeElement.dataset.row', '1')
        ->keys(':focus', ['8', '4', '0', '0', '0'])
        ->click('button:has-text("'.__('app.lab_sheets.save').'")')
        ->assertSee(__('app.lab_sheets.saved'));

    // A multi-cell paste goes through the server and fills down from the anchor (not saved).
    $page->script(<<<'JS'
        const data = new DataTransfer();
        data.setData('text/plain', "90.000\n95.000");
        document.querySelector('[data-row="0"][data-col="0"]')
            .dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
        JS);
    $page->assertSee(__('app.lab_sheets.pasted', ['count' => 2]))
        ->assertValue('[data-row="1"][data-col="0"]', '95000');

    $cost = fn (LabSheetRow $row): ?int => LabSheetCell::query()
        ->where(['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $column->id])->value('cost');

    expect($cost($first))->toBe(74_000)
        ->and($cost($second))->toBe(84_000)
        ->and($sheet->fresh()->name)->toBe('Lista nueva');
});
