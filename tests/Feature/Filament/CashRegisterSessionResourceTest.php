<?php

use App\Actions\CloseCashRegisterSession;
use App\Filament\Resources\CashRegisterSessions\CashRegisterSessionResource;
use App\Filament\Resources\CashRegisterSessions\Pages\ListCashRegisterSessions;
use App\Filament\Resources\CashRegisterSessions\Pages\ViewCashRegisterSession;
use App\Filament\Resources\CashRegisterSessions\Tables\CashRegisterSessionsTable;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Policies\CashRegisterSessionPolicy;
use App\Support\PermissionsTeam;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->cashier = User::factory()->seller()->create(['company_id' => $this->admin->company_id]);
    $this->actingAs($this->admin);
    $this->cash = PaymentMethod::where('is_default', true)->first()
        ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    $this->session = openCashRegisterSession($this->cashier, 20_000);
});

it('lets the admin close a session left open, flagged as closed by admin', function () {
    Carbon::setTestNow(now()->addDay()->startOfDay()->addHour());

    expect(collect($this->admin->company->saleReadiness())->pluck('key'))->toContain('cash_session_stale');

    Livewire::test(ViewCashRegisterSession::class, ['record' => $this->session->getRouteKey()])
        ->callAction(TestAction::make('adminClose'), [
            'counts' => [$this->cash->id => 20_000], 'cash_left' => 0, 'notes' => null,
        ])
        ->assertHasNoActionErrors();

    $session = $this->session->fresh();
    expect($session->closed_by_admin)->toBeTrue()
        ->and($session->closed_by)->toBe($this->admin->id)
        ->and(collect($this->admin->company->fresh()->saleReadiness())->pluck('key'))->not->toContain('cash_session_stale');
});

it('requires a note on an admin close over the threshold even in blind mode', function () {
    $this->admin->company->update(['blind_cash_count' => true, 'cash_difference_note_threshold' => 1_000]);

    Livewire::test(ViewCashRegisterSession::class, ['record' => $this->session->getRouteKey()])
        ->callAction(TestAction::make('adminClose'), [
            'counts' => [$this->cash->id => 25_000], 'cash_left' => 0, 'notes' => null,
        ])
        ->assertHasActionErrors(['notes']);

    expect($this->session->fresh()->closed_at)->toBeNull();

    Livewire::test(ViewCashRegisterSession::class, ['record' => $this->session->getRouteKey()])
        ->callAction(TestAction::make('adminClose'), [
            'counts' => [$this->cash->id => 25_000], 'cash_left' => 0, 'notes' => 'Sobrante de una propina',
        ])
        ->assertHasNoActionErrors();

    expect($this->session->fresh()->closed_at)->not->toBeNull();
});

it('shows the sessions still owing a note and filters them', function () {
    $this->admin->company->update(['blind_cash_count' => true, 'cash_difference_note_threshold' => 1_000]);
    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 25_000], 0, null, $this->cashier);
    $balanced = openCashRegisterSession($this->cashier, 0);
    app(CloseCashRegisterSession::class)->handle($balanced, [$this->cash->id => 0], 0, null, $this->cashier);

    Livewire::test(ListCashRegisterSessions::class)
        ->assertCanSeeTableRecords([$this->session, $balanced])
        ->filterTable('needs_note')
        ->assertCanSeeTableRecords([$this->session])
        ->assertCanNotSeeTableRecords([$balanced]);
});

it('marks a closed session as reviewed', function () {
    $this->session->update(['closed_at' => now(), 'closed_cash' => 20_000, 'expected_cash' => 20_000, 'cash_left' => 0, 'closed_by' => $this->cashier->id]);

    Livewire::test(ListCashRegisterSessions::class)
        ->callAction(TestAction::make('review')->table($this->session));

    expect($this->session->fresh())
        ->reviewed_at->not->toBeNull()
        ->reviewed_by->toBe($this->admin->id);
});

it('renders the view page of a closed session', function () {
    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 18_000], 0, 'Faltó cambio', $this->cashier);

    Livewire::test(ViewCashRegisterSession::class, ['record' => $this->session->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('review')
        ->assertActionHidden('adminClose');
});

it('prints the close report with methods, movements, expected, counted and difference', function () {
    Payment::factory()->create(['sale_id' => Sale::factory(), 'payment_method_id' => $this->cash->id, 'amount' => 30_000, 'received_by' => $this->cashier->id]);
    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 50_000], 20_000, null, $this->cashier);

    $this->get(route('documents.cash-session', $this->session))
        ->assertOk()
        ->assertSee($this->cash->name)
        ->assertSee('50.000')
        ->assertSee(__('app.pos.cash_session.deposit'))
        ->assertSee(__('app.cash_session_admin.payments_count'))
        ->assertSee(__('app.cash_report.sales_count', ['count' => 1]))
        ->assertSee(__('app.cash_report.returns_none'));
});

it('lets the owner print their own report', function () {
    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 20_000], 0, null, $this->cashier);

    $this->actingAs($this->cashier)->get(route('documents.cash-session', $this->session))->assertOk();
});

it('does not let a seller see another cashier report or the sessions resource', function () {
    $this->actingAs(User::factory()->seller()->create(['company_id' => $this->admin->company_id]));

    $this->get(route('documents.cash-session', $this->session))->assertForbidden();
    $this->get(CashRegisterSessionResource::getUrl())->assertForbidden();
});

it('does not let an admin of another company see the report', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('documents.cash-session', $this->session))->assertForbidden();
});

it('does not let the owner print the running report of a session that is still open', function () {
    $this->actingAs($this->cashier)->get(route('documents.cash-session', $this->session))->assertNotFound();

    $this->actingAs($this->admin)->get(route('documents.cash-session', $this->session))->assertOk();
});

it('hides the report action while the session is open and shows it once closed', function () {
    Livewire::test(ViewCashRegisterSession::class, ['record' => $this->session->getRouteKey()])->assertActionHidden('report');
    Livewire::test(ListCashRegisterSessions::class)->assertActionHidden(TestAction::make('report')->table($this->session));

    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 20_000], 0, null, $this->cashier);

    Livewire::test(ViewCashRegisterSession::class, ['record' => $this->session->getRouteKey()])->assertActionVisible('report');
});

it('only lets an admin of the same shop update a session', function () {
    $foreignAdmin = User::factory()->admin()->create(['company_id' => Company::factory()->create()->id]);

    // Worst case: the foreign admin holds this shop's admin role as well.
    PermissionsTeam::runAs($this->admin->company, fn () => $foreignAdmin->assignRole(User::ROLE_ADMIN));
    $policy = new CashRegisterSessionPolicy;

    expect($policy->update($this->admin, $this->session))->toBeTrue()
        ->and($policy->update($foreignAdmin, $this->session))->toBeFalse();
});

it('rejects an admin close in a blind shop without the note on the server, whatever the form says', function () {
    $this->admin->company->update(['blind_cash_count' => true, 'cash_difference_note_threshold' => 1_000]);

    expect(fn () => app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 25_000], 0, null, $this->admin))
        ->toThrow(ValidationException::class);

    expect($this->session->fresh()->closed_at)->toBeNull();
});

it('tells the admin when someone else already closed the session', function () {
    // The race window sits between the action's visibility check and the lock, so run the action on a stale model.
    $stale = CashRegisterSession::find($this->session->id);
    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 20_000], 0, null, $this->cashier);

    CashRegisterSessionsTable::adminCloseAction()->record($stale)->call([
        'data' => ['counts' => [$this->cash->id => 20_000], 'cash_left' => 0, 'notes' => null],
    ]);

    expect(collect(session('filament.notifications'))->pluck('title'))->toContain(__('app.pos.cash_session.already_closed'));
});

it('shows an unreviewed closed session with a negative icon and an open one with none', function () {
    $open = openCashRegisterSession($this->cashier, 0);
    app(CloseCashRegisterSession::class)->handle($this->session, [$this->cash->id => 20_000], 0, null, $this->cashier);

    $column = Livewire::test(ListCashRegisterSessions::class)->instance()->getTable()->getColumn('reviewed_at');

    expect($column->record($this->session->fresh())->getState())->toBeFalse()
        ->and($column->record($open)->getState())->toBeNull();
});
