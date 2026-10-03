<?php

use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\RelationManagers\PaymentsRelationManager;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->sale = Sale::factory()->create(['total' => 100_000, 'subtotal' => 100_000]);
    $this->paid = Payment::factory()->create(['sale_id' => $this->sale->id, 'amount' => 100_000]);
    $this->refund = Payment::factory()->create(['sale_id' => $this->sale->id, 'amount' => -40_000]);
});

function paymentsManager(Sale $sale)
{
    return Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => EditSale::class]);
}

it('hides edit and delete on refund payments but not on regular ones', function () {
    paymentsManager($this->sale)
        ->assertActionHidden(TestAction::make('edit')->table($this->refund))
        ->assertActionHidden(TestAction::make('delete')->table($this->refund))
        ->assertActionVisible(TestAction::make('edit')->table($this->paid))
        ->assertActionVisible(TestAction::make('delete')->table($this->paid));
});

it('never bulk deletes a refund payment', function () {
    paymentsManager($this->sale)->callTableBulkAction('delete', [$this->paid->id, $this->refund->id]);

    expect(Payment::whereKey($this->refund->id)->exists())->toBeTrue()
        ->and(Payment::whereKey($this->paid->id)->exists())->toBeFalse();
});

it('refuses to delete a refund payment in the model, so a refund cannot be paid twice', function () {
    expect(fn () => $this->refund->delete())->toThrow(ValidationException::class)
        ->and(fn () => $this->refund->forceDelete())->toThrow(ValidationException::class)
        ->and(Payment::whereKey($this->refund->id)->exists())->toBeTrue();
});

it('hides adding payments on a voided sale', function () {
    paymentsManager($this->sale)->assertActionVisible(TestAction::make(CreateAction::class)->table());

    $this->sale->update(['status' => SaleStatus::Voided]);

    paymentsManager($this->sale->fresh())->assertActionHidden(TestAction::make(CreateAction::class)->table());
});
