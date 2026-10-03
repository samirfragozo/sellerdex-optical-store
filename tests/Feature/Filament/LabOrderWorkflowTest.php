<?php

use App\Enums\FrameType;
use App\Enums\LensKind;
use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Filament\Resources\LensOrders\Pages\CreateLensOrder;
use App\Filament\Resources\LensOrders\Pages\EditLensOrder;
use App\Filament\Resources\LensOrders\Pages\ListLensOrders;
use App\Models\LensCombination;
use App\Models\LensOrder;
use App\Models\LensType;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $this->lab = Supplier::factory()->laboratory()->create(['lead_time_days' => 3]);
});

/** A pending lab order for a lens of $kind, complete for sending unless overridden. */
function workflowOrder(LensKind $kind = LensKind::SingleVision, array $overrides = []): LensOrder
{
    $type = LensType::factory()->create(['kind' => $kind]);
    $combination = LensCombination::factory()->create(['lens_type_id' => $type->id]);
    $item = SaleItem::factory()->create(['unit_cost' => 80_000]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id, 'lens_combination_id' => $combination->id]);

    return LensOrder::factory()->create([
        'sale_item_id' => $item->id, 'supplier_id' => test()->lab->id,
        'lab_status' => LensOrderStatus::PendingAssignment,
        'od_pd' => 31, 'os_pd' => 32, 'frame_type' => FrameType::FullRim,
        ...$overrides,
    ]);
}

it('sends a complete order and dates it in business days', function () {
    $order = workflowOrder();
    Carbon::setTestNow('2026-10-02 10:00'); // a Friday

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('send'))
        ->assertNotified();

    $order->refresh();
    expect($order->lab_status)->toBe(LensOrderStatus::Sent)
        ->and($order->sent_at->toDateString())->toBe('2026-10-02')
        ->and($order->expected_date->toDateString())->toBe('2026-10-07');
});

it('leaves the expected date empty when the lab has no lead time', function () {
    $this->lab->update(['lead_time_days' => null]);
    $order = workflowOrder();

    $order->markSent();

    expect($order->fresh()->expected_date)->toBeNull();
});

it('refuses to send an incomplete order and says what is missing', function () {
    $order = workflowOrder(LensKind::Progressive, ['od_pd' => null, 'od_height' => null, 'os_height' => null]);

    expect($order->missingForSending())->toBe([
        __('app.fields.od_pd'), __('app.fields.od_height'), __('app.fields.os_height'),
    ]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('send'));

    expect($order->fresh()->lab_status)->toBe(LensOrderStatus::PendingAssignment);
});

it('advances a sent order to received and then ready', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Sent]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('receive'));
    expect($order->fresh())->lab_status->toBe(LensOrderStatus::Received)
        ->and($order->fresh()->received_date->toDateString())->toBe(now()->toDateString());

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('markReady'));
    expect($order->fresh()->lab_status)->toBe(LensOrderStatus::Ready);
});

it('only offers each step from the status before it', function () {
    $order = workflowOrder();

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionVisible('send')
        ->assertActionHidden('receive')
        ->assertActionHidden('markReady')
        ->assertActionHidden('remake');
});

it('remakes a ready order as a new pending order copying the technical data', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Ready, 'od_height' => 18, 'prescription_snapshot' => ['od_sphere' => '-1.00']]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('remake'), [
            'remake_reason' => RemakeReason::Measurements->value,
            'remake_responsible' => RemakeResponsible::Store->value,
            'remake_cost' => 80_000,
        ])
        ->assertHasNoActionErrors();

    $remake = $order->remakes()->sole();
    expect($remake->lab_status)->toBe(LensOrderStatus::PendingAssignment)
        ->and($remake->sale_item_id)->toBe($order->sale_item_id)
        ->and($remake->supplier_id)->toBe($this->lab->id)
        ->and($remake->od_height)->toBe('18.0')
        ->and($remake->prescription_snapshot)->toBe(['od_sphere' => '-1.00'])
        ->and($remake->remake_cost)->toBe(80_000)
        ->and($order->saleItem->fresh()->lensOrder->is($remake))->toBeTrue()
        ->and(LensOrder::pending()->pluck('id')->all())->toBe([$remake->id]);
});

it('stores no cost for a remake the lab or the customer is responsible for', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Received]);

    $remake = $order->remake(RemakeReason::LabDefect, RemakeResponsible::Lab, 50_000);

    expect($remake->remake_cost)->toBe(0);
});

it('refuses a second remake of the same order and a remake of an unsent order', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Ready]);
    $order->remake(RemakeReason::Breakage, RemakeResponsible::Customer, 0);

    expect(fn () => $order->fresh()->remake(RemakeReason::Breakage, RemakeResponsible::Customer, 0))->toThrow(DomainException::class)
        ->and(fn () => workflowOrder()->remake(RemakeReason::Other, RemakeResponsible::Store, 1))->toThrow(DomainException::class);
});

it('requires a cost when the store is responsible for a remake', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Ready]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('remake'), [
            'remake_reason' => RemakeReason::Measurements->value,
            'remake_responsible' => RemakeResponsible::Store->value,
            'remake_cost' => null,
        ])
        ->assertHasActionErrors(['remake_cost']);
});

it('locks the technical fields once the order is sent and shows the remake origin', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Sent]);
    $remake = workflowOrder(overrides: ['remake_of_id' => $order->id, 'remake_reason' => RemakeReason::Other, 'remake_responsible' => RemakeResponsible::Lab, 'prescription_snapshot' => ['od_sphere' => '-1.00']]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->assertFormFieldDisabled('od_pd')
        ->assertFormFieldDisabled('supplier_id')
        ->assertFormFieldDisabled('lab_status');

    Livewire::test(EditLensOrder::class, ['record' => $remake->getRouteKey()])
        ->assertFormFieldEnabled('od_pd')
        ->assertSee(__('app.lab_order.remake_of', ['id' => $order->id]))
        ->assertSee('-1.00');
});

it('shows the new status in the form right after an action', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Sent]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('receive'))
        ->assertSchemaStateSet(['lab_status' => LensOrderStatus::Received->value]);
});

it('moves an order along from the list rows', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Sent]);

    Livewire::test(ListLensOrders::class)
        ->assertTableActionHidden('send', $order)
        ->callAction(TestAction::make('receive')->table($order));

    expect($order->fresh()->lab_status)->toBe(LensOrderStatus::Received);
});

it('never lets a created order start past pending, even with a tampered status', function () {
    $item = SaleItem::factory()->create();

    Livewire::test(CreateLensOrder::class)
        ->fillForm(['sale_item_id' => $item->id, 'supplier_id' => $this->lab->id])
        ->set('data.lab_status', LensOrderStatus::Ready->value)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LensOrder::sole()->lab_status)->toBe(LensOrderStatus::PendingAssignment);
});

it('sends the measurements on screen, saving unsaved edits first', function () {
    $order = workflowOrder(overrides: ['od_pd' => null]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['od_pd' => 33])
        ->callAction(TestAction::make('send'));

    expect($order->fresh())->lab_status->toBe(LensOrderStatus::Sent)
        ->and($order->fresh()->od_pd)->toBe('33.0');
});

it('does not send when the unsaved edits are invalid', function () {
    $order = workflowOrder();

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['od_pd' => 99])
        ->callAction(TestAction::make('send'));

    expect($order->fresh()->lab_status)->toBe(LensOrderStatus::PendingAssignment);
});

it('still saves the order after the lab returns it before the expected date', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Sent, 'expected_date' => now()->addDays(5)->toDateString()]);

    Livewire::test(EditLensOrder::class, ['record' => $order->getRouteKey()])
        ->callAction(TestAction::make('receive'))
        ->fillForm(['notes' => 'Llegó antes'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->fresh()->notes)->toBe('Llegó antes');
});

it('hides the workflow actions from a user who cannot update lens orders', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Sent]);
    $viewer = User::factory()->create(['company_id' => auth()->user()->company_id]);
    $viewer->givePermissionTo(['ViewAny:LensOrder', 'View:LensOrder']);

    $this->actingAs($viewer);

    Livewire::test(ListLensOrders::class)
        ->assertTableActionHidden('receive', $order);
});

it('allows only one remake per order at the database level', function () {
    $order = workflowOrder(overrides: ['lab_status' => LensOrderStatus::Ready]);
    $order->remake(RemakeReason::Other, RemakeResponsible::Lab, 0);

    expect(fn () => LensOrder::factory()->create(['sale_item_id' => $order->sale_item_id, 'remake_of_id' => $order->id]))
        ->toThrow(QueryException::class);
});
