<?php

use App\Actions\CloseCashRegisterSession;
use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashRegisterSessionCount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cashier = User::factory()->seller()->create();
    $this->actingAs($this->cashier);
    $this->cash = PaymentMethod::where('is_default', true)->first()
        ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    $this->card = PaymentMethod::factory()->create(['name' => 'Tarjeta']);
    $this->session = openCashRegisterSession($this->cashier, 50_000);
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->cash->id, 'amount' => 30_000, 'received_by' => $this->cashier->id]);
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->card->id, 'amount' => 80_000, 'received_by' => $this->cashier->id]);
});

/** Close the test session through the endpoint. */
function closeSessionRequest(array $counts, int $cashLeft, ?string $notes = null)
{
    return test()->postJson(route('pos.cash-sessions.close', test()->session), array_filter([
        'counts' => $counts, 'cash_left' => $cashLeft, 'notes' => $notes,
    ], fn ($v) => $v !== null));
}

it('closes with a count per method, keeps the float and withdraws the rest for deposit', function () {
    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 40_000)
        ->assertOk()
        ->assertJsonPath('cash_left', 40_000)
        ->assertJsonPath('counts.0.expected', 80_000);

    $session = $this->session->fresh();
    expect($session->closed_at)->not->toBeNull()
        ->and($session->closed_cash)->toBe(80_000)
        ->and($session->closed_by)->toBe($this->cashier->id)
        ->and($session->closed_by_admin)->toBeFalse()
        ->and(CashRegisterSessionCount::where('cash_register_session_id', $session->id)->count())->toBe(2)
        ->and(CashMovement::where('type', CashMovementType::Withdrawal)->sole()->amount)->toBe(40_000);
});

it('requires a note when a difference exceeds the threshold', function () {
    closeSessionRequest([$this->cash->id => 79_000, $this->card->id => 80_000], 0)
        ->assertStatus(422)->assertJsonValidationErrors('notes');

    $this->cashier->company->update(['cash_difference_note_threshold' => 2_000]);
    closeSessionRequest([$this->cash->id => 79_000, $this->card->id => 80_000], 0)->assertOk();
});

it('rejects leaving more cash than was counted, and a second close', function () {
    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 90_000)
        ->assertStatus(422)->assertJsonValidationErrors('cash_left');
    expect($this->session->fresh()->closed_at)->toBeNull();

    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 0)->assertOk();
    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 0)->assertStatus(422);

    expect(CashRegisterSessionCount::count())->toBe(2)
        ->and(CashMovement::where('type', CashMovementType::Withdrawal)->count())->toBe(1);
});

it('rejects counting a payment method that is not the shop\'s', function () {
    $foreign = PaymentMethod::withoutGlobalScopes()->create(['company_id' => User::factory()->seller()->create()->company_id, 'name' => 'Otra', 'is_active' => true]);

    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000, $foreign->id => 1], 0)
        ->assertStatus(422)->assertJsonValidationErrors('counts');
    expect($this->session->fresh()->closed_at)->toBeNull();
});

it('hides expected amounts in the preview when the count is blind, and shows them after closing', function () {
    $this->cashier->company->update(['blind_cash_count' => true]);

    $this->getJson(route('pos.cash-sessions.preview', $this->session))
        ->assertOk()
        ->assertJsonPath('blind', true)
        ->assertJsonPath('methods.0.expected', null);

    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 0)
        ->assertOk()->assertJsonPath('counts.0.expected', 80_000);
});

it('records cash in and cash out during the session', function () {
    $this->postJson(route('pos.cash-sessions.movements.store', $this->session), ['type' => 'withdrawal', 'amount' => 20_000, 'reason' => 'Pago domicilio'])
        ->assertCreated();

    expect($this->session->fresh()->expectedCash())->toBe(60_000);

    $this->postJson(route('pos.cash-sessions.movements.store', $this->session), ['type' => 'income', 'amount' => 0, 'reason' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['amount', 'reason']);
});

it('rejects a cash movement on a closed session', function () {
    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 0)->assertOk();

    $this->postJson(route('pos.cash-sessions.movements.store', $this->session), ['type' => 'income', 'amount' => 1, 'reason' => 'x'])
        ->assertStatus(422);
});

it('blocks selling from a session left open on a previous day', function () {
    Carbon::setTestNow(now()->addDay()->startOfDay()->addHour());

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'products' => [['description' => 'Estuche', 'quantity' => 1, 'unit_price' => 10_000]],
    ])->assertStatus(423)->assertJsonPath('message', __('app.pos.cash_session.stale'));
});

it('does not let a cashier close or move cash in another cashier session', function () {
    $other = User::factory()->seller()->create(['company_id' => $this->cashier->company_id]);
    $this->actingAs($other);

    closeSessionRequest([$this->cash->id => 0], 0)->assertForbidden();
    $this->postJson(route('pos.cash-sessions.movements.store', $this->session), ['type' => 'income', 'amount' => 1, 'reason' => 'x'])->assertForbidden();
});

it('shares the suggested float from the last close', function () {
    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 40_000)->assertOk();

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page->where('suggestedOpeningCash', 40_000));
});

it('closes a blind count on the first submission even when a note is missing, then takes the note afterwards', function () {
    $this->cashier->company->update(['blind_cash_count' => true]);
    $counts = [$this->cash->id => 79_000, $this->card->id => 80_000];

    closeSessionRequest($counts, 0)
        ->assertOk()
        ->assertJsonPath('requires_note', true)
        ->assertJsonPath('needs_note', true)
        ->assertJsonPath('counts.0.expected', 80_000);

    expect($this->session->fresh()->closed_at)->not->toBeNull()
        ->and(CashRegisterSessionCount::count())->toBe(2);

    closeSessionRequest($counts, 0)->assertStatus(422);
    expect(CashRegisterSessionCount::count())->toBe(2);

    $this->postJson(route('pos.cash-sessions.note', $this->session), ['notes' => ''])->assertStatus(422)->assertJsonValidationErrors('notes');
    $this->postJson(route('pos.cash-sessions.note', $this->session), ['notes' => 'Faltó un billete'])
        ->assertOk()->assertJsonPath('needs_note', false);

    expect($this->session->fresh()->notes)->toBe('Faltó un billete');
    $this->postJson(route('pos.cash-sessions.note', $this->session), ['notes' => 'otra'])->assertStatus(422);
});

it('does not ask for a note after a blind close that matched', function () {
    $this->cashier->company->update(['blind_cash_count' => true]);

    closeSessionRequest([$this->cash->id => 80_000, $this->card->id => 80_000], 0)
        ->assertOk()->assertJsonPath('requires_note', false);
});

it('keeps rejecting a missing note before closing when the count is not blind', function () {
    closeSessionRequest([$this->cash->id => 79_000, $this->card->id => 80_000], 0)->assertStatus(422);

    expect($this->session->fresh()->closed_at)->toBeNull();
});

it('still rejects a cash left above the counted cash before closing a blind count', function () {
    $this->cashier->company->update(['blind_cash_count' => true]);

    closeSessionRequest([$this->cash->id => 79_000, $this->card->id => 80_000], 90_000)->assertStatus(422)->assertJsonValidationErrors('cash_left');
    expect($this->session->fresh()->closed_at)->toBeNull();
});

it('only lets the owner add the note, and only when one is needed', function () {
    $this->cashier->company->update(['blind_cash_count' => true]);
    closeSessionRequest([$this->cash->id => 79_000, $this->card->id => 80_000], 0)->assertOk();

    $this->actingAs(User::factory()->seller()->create(['company_id' => $this->cashier->company_id]));
    $this->postJson(route('pos.cash-sessions.note', $this->session), ['notes' => 'x'])->assertForbidden();
});

it('rejects a note on an open session', function () {
    $this->postJson(route('pos.cash-sessions.note', $this->session), ['notes' => 'x'])->assertStatus(422);
});

it('rejects count keys that are not plain method ids', function (string $key) {
    $this->postJson(route('pos.cash-sessions.close', $this->session), ['counts' => [$key => 5, $this->cash->id => 80_000], 'cash_left' => 0])
        ->assertStatus(422)->assertJsonValidationErrors('counts');
    expect($this->session->fresh()->closed_at)->toBeNull();
})->with(['5abc', '05', '0', '-1']);

it('records an admin closing the session of a cashier of the same company', function () {
    $admin = User::factory()->admin()->create(['company_id' => $this->cashier->company_id]);

    $closed = app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 80_000, $this->card->id => 80_000], 0, null, $admin);

    expect($closed->closed_by)->toBe($admin->id)
        ->and($closed->closed_by_admin)->toBeTrue();
});

it('throws on a second direct close of the same session', function () {
    $counts = [$this->cash->id => 80_000, $this->card->id => 80_000];
    $action = app(CloseCashRegisterSession::class);
    $action->handle($this->session, $counts, 0, null, $this->cashier);

    try {
        $action->handle($this->session, $counts, 0, null, $this->cashier);
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('session');
    }
});

it('shares the note still owed after a blind close until the cashier saves it', function () {
    $this->cashier->company->update(['blind_cash_count' => true]);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page->where('pendingCashNote', null));

    closeSessionRequest([$this->cash->id => 79_000, $this->card->id => 80_000], 0)->assertOk();

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('pendingCashNote.id', $this->session->id)
        ->where('pendingCashNote.counts.0.counted', 79_000));

    $this->postJson(route('pos.cash-sessions.note', $this->session), ['notes' => 'Faltó un billete'])->assertOk();

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page->where('pendingCashNote', null));
});
