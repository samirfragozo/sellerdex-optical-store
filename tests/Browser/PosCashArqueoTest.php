<?php

use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cashMethodFor(User $cashier): PaymentMethod
{
    return PaymentMethod::where('company_id', $cashier->company_id)->where('is_default', true)->first()
        ?? PaymentMethod::factory()->create(['company_id' => $cashier->company_id, 'is_default' => true, 'name' => 'Efectivo', 'is_active' => true]);
}

it('opens with the suggested float, moves cash and closes counting by denomination', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $cashier = User::factory()->seller()->create();
    $cash = cashMethodFor($cashier);
    CashRegisterSession::factory()->for($cashier)->create([
        'company_id' => $cashier->company_id, 'opened_at' => now()->subDay(), 'opening_cash' => 0,
        'closed_at' => now()->subDay()->addHours(8), 'closed_cash' => 70_000, 'expected_cash' => 70_000, 'cash_left' => 50_000,
    ]);

    $this->actingAs($cashier);

    $page = visit('/pos')
        ->assertValue('#opening_cash', '50000')
        ->click('button:has-text("Abrir caja")')
        ->wait(1);

    $session = CashRegisterSession::whereNull('closed_at')->sole();
    Payment::factory()->create(['sale_id' => Sale::factory()->create(['company_id' => $cashier->company_id]), 'payment_method_id' => $cash->id,
        'amount' => 30_000, 'received_by' => $cashier->id, 'company_id' => $cashier->company_id]);

    $page->click('[data-test="user-menu-trigger"]')
        ->click('text=Movimiento de caja')
        ->click('text=Retiro')
        ->fill('#cash_movement_amount', '10000')
        ->fill('#cash_movement_reason', 'Pago domicilio')
        ->click('button:has-text("Registrar movimiento")')
        ->wait(1)
        ->click('[data-test="user-menu-trigger"]')
        ->click('text=Cerrar caja')
        ->click('text=Por denominación')
        ->fill('[aria-label="Billetes de $50.000"]', '1')
        ->fill('[aria-label="Billetes de $20.000"]', '1')
        ->fill('#cash_left', '20000')
        ->click('button:has-text("Cerrar caja")')
        ->assertSee('$70.000')
        ->assertNoJavaScriptErrors();

    $session->refresh();
    expect($session->closed_cash)->toBe(70_000)
        ->and($session->cash_left)->toBe(20_000)
        ->and(CashMovement::where('reason', 'Pago domicilio')->exists())->toBeTrue();
});

it('blocks a cashier whose session is open since a previous day until it is closed', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $cashier = User::factory()->seller()->create();
    cashMethodFor($cashier);
    CashRegisterSession::factory()->for($cashier)->create(['company_id' => $cashier->company_id, 'opened_at' => now()->subDay(), 'opening_cash' => 0]);

    $this->actingAs($cashier);

    visit('/pos')
        ->assertSee('Tu caja sigue abierta desde un día anterior')
        ->click('button:has-text("Cerrar caja ahora")')
        ->fill('#cash_total', '0')
        ->fill('#cash_left', '0')
        ->click('button:has-text("Cerrar caja")')
        ->click('button:has-text("Listo")')
        ->assertSee('Abrir caja')
        ->assertNoJavaScriptErrors();
});

it('freezes a blind count on the first submission and requires the note before finishing', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $cashier = User::factory()->seller()->create();
    $cashier->company->update(['blind_cash_count' => true]);
    cashMethodFor($cashier);
    $session = CashRegisterSession::factory()->for($cashier)->create(['company_id' => $cashier->company_id, 'opened_at' => now(), 'opening_cash' => 10_000]);

    $this->actingAs($cashier);

    $page = visit('/pos')
        ->click('[data-test="user-menu-trigger"]')
        ->click('text=Cerrar caja')
        ->assertDontSee('Esperado')
        ->fill('#cash_total', '4000')
        ->fill('#cash_left', '0')
        ->click('button:has-text("Cerrar caja")')
        ->assertSee('Hay una diferencia: explica el motivo para terminar.')
        ->assertDisabled('button:has-text("Listo")')
        ->fill('#close_note', 'Faltó un billete')
        ->click('button:has-text("Guardar nota")')
        ->wait(1)
        ->assertEnabled('button:has-text("Listo")')
        ->assertNoJavaScriptErrors();

    expect($session->fresh()->closed_at)->not->toBeNull()
        ->and($session->fresh()->notes)->toBe('Faltó un billete');
});
