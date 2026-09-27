<?php

use App\Models\Company;
use App\Models\Sale;
use App\Models\User;

function saleFor(Company $company): Sale
{
    test()->actingAs(User::factory()->forCompany($company)->seller()->create());

    return Sale::factory()->create();
}

it('numbers sales consecutively per company even when companies interleave', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();

    $numbers = [
        saleFor($a)->number,
        saleFor($b)->number,
        saleFor($a)->number,
        saleFor($b)->number,
        saleFor($a)->number,
    ];

    expect($numbers)->toBe(['000001', '000001', '000002', '000002', '000003'])
        ->and($a->fresh()->next_sale_number)->toBe(4)
        ->and($b->fresh()->next_sale_number)->toBe(3);
});

it('applies the company prefix', function () {
    $company = Company::factory()->create(['sale_number_prefix' => 'OPT-']);

    expect(saleFor($company)->number)->toBe('OPT-000001');
});

it('never reuses a number after a sale is deleted', function () {
    $company = Company::factory()->create();
    $first = saleFor($company);
    $first->delete();

    expect(saleFor($company)->number)->toBe('000002');
});

it('keeps an explicitly given number', function () {
    $company = Company::factory()->create();
    $this->actingAs(User::factory()->forCompany($company)->seller()->create());

    expect(Sale::factory()->create(['number' => 'MANUAL-1'])->number)->toBe('MANUAL-1')
        ->and($company->fresh()->next_sale_number)->toBe(1);
});
