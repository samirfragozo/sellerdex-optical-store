<?php

use App\Enums\TaxTreatment;
use App\Enums\VatRegime;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\RelationManagers\ItemsRelationManager;
use App\Models\Customer;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\Tax;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    Tax::seedDefaultsFor($this->admin->company);
    $this->frame = Product::factory()->create(['price' => 119_000, 'tax_id' => Tax::where('name', 'IVA 19%')->sole()->id]);
    $this->sale = Sale::factory()->create();
});

function addSaleLine(Sale $sale, Product $product): void
{
    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => EditSale::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 119_000,
            'unit_cost' => 0,
        ])
        ->assertHasNoErrors();
}

it('snapshots the product tax on a line added from the admin', function () {
    addSaleLine($this->sale, $this->frame);

    $line = $this->sale->items()->sole();

    expect($line->tax_name)->toBe('IVA 19%')
        ->and((float) $line->tax_rate)->toBe(19.0)
        ->and($line->tax_treatment)->toBe(TaxTreatment::Taxed)
        ->and($line->tax_amount)->toBe(19_000)
        ->and($this->sale->fresh()->tax_amount)->toBe(19_000);
});

it('snapshots no tax for a company that is not VAT responsible', function () {
    $this->admin->company->update(['vat_regime' => VatRegime::NotResponsible]);

    addSaleLine($this->sale, $this->frame);

    $line = $this->sale->items()->sole();

    expect($line->tax_name)->toBeNull()
        ->and($line->tax_amount)->toBe(0);
});

it('re-snapshots the tax when an admin changes the line product', function () {
    $untaxed = Product::factory()->create(['tax_id' => null]);
    addSaleLine($this->sale, $untaxed);
    $line = $this->sale->items()->sole();

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $this->sale, 'pageClass' => EditSale::class])
        ->callAction(TestAction::make(EditAction::class)->table($line), [
            'product_id' => $this->frame->id,
        ])
        ->assertHasNoErrors();

    expect($line->fresh()->tax_name)->toBe('IVA 19%')
        ->and($line->fresh()->tax_amount)->toBe(19_000);
});

it('shows the armado patient and a formula link on lens lines', function () {
    $patient = Customer::factory()->create(['name' => 'Luis', 'last_name' => 'Pérez']);
    $prescription = Prescription::factory()->create(['customer_id' => $patient->id]);
    $item = SaleItem::factory()->create(['sale_id' => $this->sale->id]);
    SaleItemLensConfig::factory()->create([
        'sale_item_id' => $item->id,
        'patient_id' => $patient->id,
        'prescription_id' => $prescription->id,
    ]);

    Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $this->sale, 'pageClass' => EditSale::class])
        ->assertSee('Luis Pérez')
        ->assertSee(route('documents.formula', $prescription), false);
});
