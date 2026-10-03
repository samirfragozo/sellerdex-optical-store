<?php

use App\Enums\StockMovementType;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\StockMovementsRelationManager;
use App\Filament\Widgets\LowStockWidget;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->product = Product::factory()->create(['is_stockable' => true, 'stock' => 10]);
});

it('adjusts the stock to the counted quantity with a reason', function () {
    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->callAction(TestAction::make('adjustStock'), ['counted' => 7, 'reason' => 'Conteo de fin de mes'])
        ->assertHasNoActionErrors();

    $movement = StockMovement::sole();
    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe(-3)
        ->and($movement->balance_after)->toBe(7)
        ->and($movement->reason)->toBe('Conteo de fin de mes')
        ->and($this->product->fresh()->stock)->toBe(7);
});

it('requires a reason and a count different from the current stock', function () {
    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->callAction(TestAction::make('adjustStock'), ['counted' => 7, 'reason' => ''])
        ->assertHasActionErrors(['reason']);

    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->callAction(TestAction::make('adjustStock'), ['counted' => 10, 'reason' => 'x'])
        ->assertHasActionErrors(['counted']);

    expect(StockMovement::count())->toBe(0);
});

it('never changes the stock by saving the product form', function () {
    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->fillForm(['name' => 'Estuche nuevo', 'stock' => 99])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->product->fresh())->name->toBe('Estuche nuevo')->stock->toBe(10);
});

it('records the stock typed on creation as an initial movement', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Paño', 'price' => 2000, 'cost' => 500, 'product_category_id' => $this->product->product_category_id, 'is_stockable' => true, 'is_active' => true, 'stock' => 12])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Product::where('name', 'Paño')->sole();
    expect($created->stock)->toBe(12)
        ->and($created->stockMovements()->sole()->type)->toBe(StockMovementType::Initial);
});

it('shows the kardex of a stockable product', function () {
    $this->product->stockMovements()->create([
        'company_id' => $this->product->company_id, 'type' => StockMovementType::Adjustment,
        'quantity' => -1, 'balance_after' => 9, 'reason' => 'Rotura',
    ]);

    Livewire::test(StockMovementsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->assertSee('Rotura')
        ->assertCanSeeTableRecords($this->product->stockMovements);
});

it('labels a movement by its sale number and survives a source that no longer exists', function () {
    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 1]);
    $gone = $this->product->stockMovements()->create([
        'company_id' => $this->product->company_id, 'type' => StockMovementType::Sale,
        'quantity' => -1, 'balance_after' => 8, 'source_type' => SaleItem::class, 'source_id' => 999999,
    ]);

    Livewire::test(StockMovementsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
        ->assertSee(__('app.inventory.sale_source', ['number' => $sale->number]))
        ->assertCanSeeTableRecords($this->product->stockMovements)
        ->assertSee($gone->balance_after);
});

it('hides stock, the kardex, the adjustment and the low-stock widget when the shop does not track inventory', function () {
    $this->admin->company->update(['tracks_inventory' => false]);

    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->assertActionHidden('adjustStock')
        ->assertFormFieldIsHidden('stock')
        ->assertFormFieldIsHidden('is_stockable');

    expect(StockMovementsRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeFalse()
        ->and(LowStockWidget::canView())->toBeFalse();
});

it('does not send stock to the POS when the shop does not track inventory', function () {
    $this->admin->company->update(['tracks_inventory' => false]);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('products.data', fn ($products) => collect($products)->every(fn ($p) => $p['stock'] === null)));
});

it('does not offer the adjustment on a trashed product', function () {
    $this->product->delete();

    Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
        ->assertActionHidden('adjustStock');
});

it('does not offer the adjustment or the kardex to a seller', function () {
    $this->actingAs(User::factory()->seller()->forCompany($this->admin->company)->create());

    expect(StockMovementsRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeFalse();

    Livewire::test(ListProducts::class)
        ->assertTableActionHidden('adjustStock', $this->product);
});

it('records no initial movement when the shop does not track inventory', function () {
    $this->admin->company->update(['tracks_inventory' => false]);

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Paño', 'price' => 2000, 'cost' => 500, 'product_category_id' => $this->product->product_category_id, 'is_active' => true, 'stock' => 12])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Product::where('name', 'Paño')->sole();
    expect($created->stock)->not->toBe(12)
        ->and(StockMovement::count())->toBe(0);
});

it('records no initial movement for a product that is not stockable', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Servicio', 'price' => 2000, 'cost' => 500, 'product_category_id' => $this->product->product_category_id, 'is_stockable' => false, 'is_active' => true, 'stock' => 12])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::where('name', 'Servicio')->sole()->stock)->not->toBe(12)
        ->and(StockMovement::count())->toBe(0);
});

it('shows the stock column only when the shop tracks inventory', function () {
    Livewire::test(ListProducts::class)->assertTableColumnVisible('stock');

    $this->admin->company->update(['tracks_inventory' => false]);

    Livewire::test(ListProducts::class)->assertTableColumnHidden('stock');
});

it('keeps a new product stockable by default, so the typed initial stock is recorded', function () {
    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Paño', 'price' => 2000, 'cost' => 500, 'product_category_id' => $this->product->product_category_id, 'is_active' => true, 'stock' => 12])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Product::where('name', 'Paño')->sole();
    expect($created->is_stockable)->toBeTrue()
        ->and($created->stock)->toBe(12)
        ->and($created->stockMovements()->sole()->type)->toBe(StockMovementType::Initial);
});
