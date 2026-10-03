<?php

use App\Actions\SeedCompanyDefaults;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\RelationManagers\PaymentsRelationManager;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->credit = PaymentMethod::storeCreditFor($this->admin->company_id)
        ?? PaymentMethod::factory()->create(['name' => 'Saldo a favor', 'is_store_credit' => true]);
    $this->customer = Customer::factory()->create();
});

it('grants credit with a negative store-credit payment and spends it with a positive one', function () {
    $old = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $old->id, 'payment_method_id' => $this->credit->id, 'amount' => -30_000]);
    expect($this->customer->creditBalance())->toBe(30_000);

    $new = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $new->id, 'payment_method_id' => $this->credit->id, 'amount' => 20_000]);
    expect($this->customer->fresh()->creditBalance())->toBe(10_000);
});

it('refuses to spend more credit than the customer has, or on a sale without customer', function () {
    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);

    expect(fn () => Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => 1]))
        ->toThrow(ValidationException::class);

    $anonymous = Sale::factory()->create(['customer_id' => null]);
    expect(fn () => Payment::factory()->create(['sale_id' => $anonymous->id, 'payment_method_id' => $this->credit->id, 'amount' => -5_000]))
        ->toThrow(ValidationException::class)
        ->and(Payment::count())->toBe(0);
});

it('gives the credit back when a store-credit payment is deleted', function () {
    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => -30_000]);
    $spend = Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => 10_000]);
    expect($this->customer->creditBalance())->toBe(20_000);

    $spend->delete();

    expect($this->customer->fresh()->creditBalance())->toBe(30_000);
});

it('keeps the store-credit method undeletable and out of the cash count', function () {
    expect($this->credit->delete())->toBeFalse()
        ->and($this->credit->isProtected())->toBeTrue();

    $session = openCashRegisterSession($this->admin, 0);
    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => -5_000, 'received_by' => $this->admin->id]);

    expect(Payment::first()->cash_register_session_id)->toBe($session->id)
        ->and(array_keys($session->fresh()->expectedByMethod()))->not->toContain($this->credit->id);
});

it('seeds the Saldo a favor method for a new company', function () {
    $company = Company::factory()->create();
    app(SeedCompanyDefaults::class)->handle($company);

    $method = PaymentMethod::storeCreditFor($company->id);

    expect($method)->not->toBeNull()
        ->and($method->name)->toBe('Saldo a favor')
        ->and($method->is_default)->toBeFalse()
        ->and(PaymentMethod::storeCreditFor($this->admin->company_id)?->company_id)->toBe($this->admin->company_id);
});

/** A sale for the customer with a 30.000 grant and a 10.000 spend of store credit; balance 20.000. */
function creditedPayments(): array
{
    $sale = Sale::factory()->create(['customer_id' => test()->customer->id]);
    $grant = Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => test()->credit->id, 'amount' => -30_000]);
    $spend = Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => test()->credit->id, 'amount' => 10_000]);

    return [$sale, $grant, $spend];
}

it('rejects editing the amount, method or sale of a store-credit payment and leaves the ledger alone', function () {
    [$sale, , $spend] = creditedPayments();
    $cash = PaymentMethod::factory()->create();
    $other = Sale::factory()->create(['customer_id' => $this->customer->id]);

    expect(fn () => $spend->fresh()->update(['amount' => 15_000]))->toThrow(ValidationException::class)
        ->and(fn () => $spend->fresh()->update(['payment_method_id' => $cash->id]))->toThrow(ValidationException::class)
        ->and(fn () => $spend->fresh()->update(['sale_id' => $other->id]))->toThrow(ValidationException::class);

    $card = Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $cash->id, 'amount' => 5_000]);
    expect(fn () => $card->fresh()->update(['payment_method_id' => $this->credit->id]))->toThrow(ValidationException::class);

    $spend->fresh()->update(['notes' => 'still editable']);

    expect($this->customer->creditBalance())->toBe(20_000)
        ->and($card->fresh()->payment_method_id)->toBe($cash->id);
});

it('does not offer editing a store-credit payment in the sale payments table', function () {
    [$sale, , $spend] = creditedPayments();
    $card = Payment::factory()->create(['sale_id' => $sale->id, 'amount' => 5_000]);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $sale, 'pageClass' => EditSale::class])
        ->assertActionHidden(TestAction::make('edit')->table($spend))
        ->assertActionVisible(TestAction::make('edit')->table($card));
});

it('runs every store-credit write inside a transaction', function () {
    $levels = [];
    Payment::creating(function () use (&$levels) {
        $levels[] = DB::transactionLevel();
    });
    $baseline = DB::transactionLevel();

    $sale = Sale::factory()->create(['customer_id' => $this->customer->id]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => -1_000]);

    expect($levels)->toContain($baseline + 1);
});

it('cannot double-reverse a force-deleted trashed payment', function () {
    [, , $spend] = creditedPayments();

    $spend->delete();
    expect($this->customer->creditBalance())->toBe(30_000);

    $spend->forceDelete();
    expect($this->customer->creditBalance())->toBe(30_000);
});

it('re-debits a restored store-credit payment once and refuses it when no longer covered', function () {
    [$sale, $grant, $spend] = creditedPayments();

    $spend->delete();
    $spend->restore();
    expect($this->customer->creditBalance())->toBe(20_000);

    $spend->restore();
    expect($this->customer->creditBalance())->toBe(20_000);

    $spend->delete();
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => 30_000]);
    expect(fn () => $spend->restore())->toThrow(ValidationException::class)
        ->and($this->customer->creditBalance())->toBe(0);
});

it('credits the reversal to the customer of the original ledger entry', function () {
    [$sale, , $spend] = creditedPayments();
    $other = Customer::factory()->create();
    $sale->update(['customer_id' => $other->id]);

    $spend->delete();

    expect($this->customer->creditBalance())->toBe(30_000)
        ->and($other->creditBalance())->toBe(0);
});

it('refuses to delete a grant that would leave the customer below zero', function () {
    [$sale, $grant] = creditedPayments();
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $this->credit->id, 'amount' => 20_000]);
    expect($this->customer->creditBalance())->toBe(0);

    expect(fn () => $grant->delete())->toThrow(ValidationException::class)
        ->and($grant->fresh()->trashed())->toBeFalse()
        ->and($this->customer->creditBalance())->toBe(0);
});

it('reverses a store-credit payment only once when it is deleted twice', function () {
    [, , $spend] = creditedPayments();
    $stale = Payment::find($spend->id);

    $spend->delete();
    $stale->delete();
    $spend->delete();

    expect($this->customer->creditBalance())->toBe(30_000)
        ->and(CustomerCredit::where('source_id', $spend->id)->where('amount', '>', 0)->count())->toBe(1);
});
