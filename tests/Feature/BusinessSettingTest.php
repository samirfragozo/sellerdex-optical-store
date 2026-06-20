<?php

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('devuelve siempre el mismo registro singleton', function () {
    $a = BusinessSetting::current();
    $b = BusinessSetting::current();

    expect($a->id)->toBe($b->id)
        ->and(BusinessSetting::count())->toBe(1);
});
