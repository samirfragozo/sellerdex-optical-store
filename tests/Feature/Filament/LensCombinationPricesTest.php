<?php

use App\Filament\Resources\LensCombinations\Pages\EditLensCombination;
use App\Filament\Resources\LensCombinations\RelationManagers\CellsRelationManager;
use App\Models\LensCombination;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->lab = Supplier::factory()->laboratory()->create(['name' => 'Lab Central']);
    $this->combination = LensCombination::factory()->unpriced()->create();
});

it('lists each lab cell costing the lens, read-only', function () {
    $cell = labCost($this->combination, $this->lab, ['cost' => 50_000]);
    $cell->column->update(['label' => 'Classic']);
    $cell->row->update(['label' => 'Poly Blue Cut']);

    Livewire::test(CellsRelationManager::class, ['ownerRecord' => $this->combination, 'pageClass' => EditLensCombination::class])
        ->assertCanSeeTableRecords([$cell])
        ->assertSee(['Lab Central', 'Classic', 'Poly Blue Cut'])
        ->assertActionDoesNotExist(TestAction::make('create')->table())
        ->assertActionExists(TestAction::make('edit_sheet')->table($cell));
});
