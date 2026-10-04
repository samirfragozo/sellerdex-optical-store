<?php

use App\Enums\FiscalResponsibility;
use App\Enums\PersonType;
use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists the fiscal fields a customer still lacks, after what the checkout sends', function () {
    $customer = Customer::factory()->create(['email' => 'ana@example.com', 'person_type' => null, 'dane_municipality_code' => null, 'fiscal_responsibilities' => null]);

    expect($customer->missingFiscalData())->toBe(['person_type', 'dane_municipality_code', 'fiscal_responsibilities'])
        ->and($customer->missingFiscalData(['person_type' => 'natural', 'dane_municipality_code' => '11001', 'fiscal_responsibilities' => ['R-99-PN']]))->toBe([]);
});

it('treats an empty responsibilities list as missing', function () {
    $customer = Customer::factory()->create([
        'email' => 'a@b.co', 'person_type' => PersonType::Natural, 'dane_municipality_code' => '05001', 'fiscal_responsibilities' => [],
    ]);

    expect($customer->missingFiscalData())->toBe(['fiscal_responsibilities']);
});

it('casts the person type and responsibilities', function () {
    $customer = Customer::factory()->create(['person_type' => 'legal', 'fiscal_responsibilities' => ['O-13', 'O-23']]);

    expect($customer->fresh()->person_type)->toBe(PersonType::Legal)
        ->and($customer->fresh()->fiscal_responsibilities)->toBe(['O-13', 'O-23'])
        ->and(FiscalResponsibility::options())->toHaveKey('R-99-PN');
});
