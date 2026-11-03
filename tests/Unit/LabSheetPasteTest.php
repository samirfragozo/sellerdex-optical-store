<?php

use App\Support\LabSheetPaste;

it('parses a tab separated block with Colombian prices', function () {
    expect(LabSheetPaste::parse("$ 42.000\t\$ 74.000\n53.000\t\n 1.250.000 \tN/A\n"))
        ->toBe([[42_000, 74_000], [53_000, null], [1_250_000, null]]);
});

it('treats a single value as a one-cell block', function () {
    expect(LabSheetPaste::parse('84000'))->toBe([[84_000]]);
});
