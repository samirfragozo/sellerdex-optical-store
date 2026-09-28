<?php

use App\Enums\PrismBase;
use App\Models\Company;
use App\Models\Prescription;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('stores refraction as numbers, including a plano eye', function () {
    $rx = Prescription::factory()->create([
        'od_sphere' => '-1.25', 'od_cylinder' => '-0.50', 'od_axis' => 90, 'od_add' => null,
        'os_sphere' => '0', 'os_cylinder' => null, 'os_axis' => null,
        'od_prism' => '1.50', 'od_prism_base' => PrismBase::In, 'od_pd' => '31.5',
    ])->fresh();

    expect($rx->od_sphere)->toBe('-1.25')
        ->and($rx->os_sphere)->toBe('0.00')
        ->and($rx->od_axis)->toBe(90)
        ->and($rx->od_prism_base)->toBe(PrismBase::In)
        ->and($rx->od_pd)->toBe('31.5')
        ->and(Prescription::formatDiopter($rx->od_sphere))->toBe('-1.25')
        ->and(Prescription::formatDiopter('1.5'))->toBe('+1.50')
        ->and(Prescription::formatDiopter($rx->os_sphere))->toBe('0.00')
        ->and(Prescription::formatDiopter(null))->toBe('');
});

it('expires after the company validity, clamping to the end of short months', function () {
    $this->admin->company->update(['prescription_validity_months' => 1]);

    $rx = Prescription::factory()->create(['exam_date' => '2026-01-31']);

    expect($rx->fresh()->expires_at->toDateString())->toBe('2026-02-28');
});

it('defaults to a 12-month validity and knows when it is expired', function () {
    $rx = Prescription::factory()->create(['exam_date' => now()->subMonths(13)->toDateString()]);
    $fresh = Prescription::factory()->create(['exam_date' => now()->subMonth()->toDateString()]);

    expect($rx->isExpired())->toBeTrue()
        ->and($fresh->isExpired())->toBeFalse()
        ->and(Company::factory()->create()->fresh()->prescription_validity_months)->toBe(12);
});

it('recomputes the expiry when the exam date changes', function () {
    $rx = Prescription::factory()->create(['exam_date' => '2026-01-10']);
    $rx->update(['exam_date' => '2026-03-10']);

    expect($rx->fresh()->expires_at->toDateString())->toBe('2027-03-10');
});

it('translates every prism base', function () {
    foreach (PrismBase::cases() as $base) {
        expect($base->label())->not->toStartWith('app.');
    }
});

it('normalizes comma decimals and blanks before storing them', function () {
    $rx = Prescription::factory()->create([
        'od_sphere' => ' +1,25 ', 'os_pd' => '31,5', 'od_prism' => '0,75', 'os_add' => '',
    ])->fresh();

    expect($rx->od_sphere)->toBe('1.25')
        ->and($rx->os_pd)->toBe('31.5')
        ->and($rx->od_prism)->toBe('0.75')
        ->and($rx->os_add)->toBeNull();
});

it('keeps an existing expiry when only the company validity and other fields change', function () {
    $rx = Prescription::factory()->create(['exam_date' => '2026-01-10']);
    $this->admin->company->update(['prescription_validity_months' => 6]);

    $rx->update(['notes' => 'Control en 6 meses']);

    expect($rx->fresh()->expires_at->toDateString())->toBe('2027-01-10');
});
