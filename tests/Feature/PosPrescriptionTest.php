<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\Prescription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    $this->customer = Customer::factory()->create();
});

function posRxPayload(array $extra = []): array
{
    return [
        'customer_id' => test()->customer->id,
        'exam_date' => now()->subWeek()->toDateString(),
        'prescriber_name' => 'Dra. Ana Gómez',
        'od_sphere' => '-1.25', 'od_cylinder' => '-0.50', 'od_axis' => 90, 'od_pd' => '31.5',
        'os_sphere' => '-1.00',
        ...$extra,
    ];
}

it('saves an external prescription with its photo from the POS', function () {
    $response = $this->post(route('pos.prescriptions.store'), posRxPayload([
        'attachment' => UploadedFile::fake()->image('formula.jpg'),
    ]), ['Accept' => 'application/json'])->assertCreated();

    $rx = Prescription::findOrFail($response->json('id'));
    expect($rx->prescriber_name)->toBe('Dra. Ana Gómez')
        ->and($rx->od_sphere)->toBe('-1.25')
        ->and($rx->expires_at)->not->toBeNull()
        ->and($response->json('is_expired'))->toBeFalse();
    Storage::disk('local')->assertExists($rx->attachment);
    expect(str_starts_with($rx->attachment, 'prescriptions/'))->toBeTrue();
});

it('validates diopter steps, axis with cylinder and the prescriber', function () {
    $this->postJson(route('pos.prescriptions.store'), posRxPayload([
        'prescriber_name' => '', 'od_sphere' => '-1.30', 'od_axis' => null,
    ]))->assertStatus(422)->assertJsonValidationErrors(['prescriber_name', 'od_sphere', 'od_axis']);
});

it('rejects a customer from another company', function () {
    $foreign = Customer::factory()->for(Company::factory()->create())->create();

    $this->postJson(route('pos.prescriptions.store'), posRxPayload(['customer_id' => $foreign->id]))
        ->assertStatus(422)->assertJsonValidationErrors('customer_id');
});

it('requires a prescription of the same customer to sell lenses', function () {
    $other = Prescription::factory()->create(); // another customer
    Supplier::factory()->laboratory()->create();
    $combination = LensCombination::factory()->create(['price' => 100_000]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $this->customer->id,
        'armados' => [['prescription_id' => $other->id, 'lens' => [
            'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
            'lens_type_id' => $combination->lens_type_id, 'lens_technology_id' => $combination->lens_technology_id,
            'lens_material_id' => $combination->lens_material_id,
        ], 'own_frame' => true]],
    ])->assertStatus(422)->assertJsonValidationErrors('armados.0.prescription_id');
});

it('lists expired prescriptions with a flag so the POS can warn', function () {
    Prescription::factory()->create(['customer_id' => $this->customer->id, 'exam_date' => now()->subMonths(14)->toDateString()]);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page->where('prescriptions.0.is_expired', true));
});

it('rejects an exam date older than two years', function () {
    $this->postJson(route('pos.prescriptions.store'), posRxPayload([
        'exam_date' => now()->subYears(3)->toDateString(),
    ]))->assertStatus(422)->assertJsonValidationErrors('exam_date');
});

it('requires a prism base when a prism is provided', function () {
    $this->postJson(route('pos.prescriptions.store'), posRxPayload([
        'od_prism' => '2.00', 'od_prism_base' => null,
    ]))->assertStatus(422)->assertJsonValidationErrors('od_prism_base');
});

it('requires the cylinder when an axis is provided', function () {
    $this->postJson(route('pos.prescriptions.store'), posRxPayload([
        'od_cylinder' => null,
    ]))->assertStatus(422)->assertJsonValidationErrors('od_cylinder');
});

it('rejects a pupillary distance off its 0.5 step and an overlong visual acuity value', function () {
    $this->postJson(route('pos.prescriptions.store'), posRxPayload([
        'od_pd' => '32.3', 'od_va' => str_repeat('x', 11),
    ]))->assertStatus(422)->assertJsonValidationErrors(['od_pd', 'od_va']);
});
