<?php

use App\Models\LensCombinationPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('widens old all-prescription rows to the manufacturing limits and leaves custom ranges alone', function () {
    $this->actingAs(User::factory()->admin()->create());
    $all = LensCombinationPrice::factory()->create(['sphere_min' => -20, 'sphere_max' => 20, 'cylinder_min' => -10, 'cylinder_max' => 10]);
    $custom = LensCombinationPrice::factory()->create(['sphere_min' => -20, 'sphere_max' => -6.25, 'cylinder_min' => -10, 'cylinder_max' => 10]);

    (require database_path('migrations/2026_11_02_100001_widen_all_prescription_lens_price_ranges.php'))->up();

    expect([(float) $all->refresh()->sphere_min, (float) $all->sphere_max, (float) $all->cylinder_min, (float) $all->cylinder_max])->toBe([-25.0, 20.0, -8.0, 8.0])
        ->and((float) $custom->refresh()->sphere_max)->toBe(-6.25)
        ->and((float) $custom->cylinder_min)->toBe(-10.0);
});
