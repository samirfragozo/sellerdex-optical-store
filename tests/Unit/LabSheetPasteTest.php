<?php

use App\Support\LabSheetPaste;

it('parses a tab separated block with Colombian prices', function () {
    expect(LabSheetPaste::parse("$ 42.000\t\$ 74.000\n53.000\t\n 1.250.000 \tN/A\n"))
        ->toBe([[42_000, 74_000], [53_000, null], [1_250_000, null]]);
});

it('treats a single value as a one-cell block', function () {
    expect(LabSheetPaste::parse('84000'))->toBe([[84_000]]);
});

it('parses pasted text into costs', function (string $text, array $expected) {
    expect(LabSheetPaste::parse($text))->toBe($expected);
})->with([
    'es-CO currency with cents' => ['$ 42.000,00', [[42_000]]],
    'comma thousands' => ['1,250,000', [[1_250_000]]],
    'one decimal digit' => ['84.000,5', [[84_000]]],
    'CRLF block' => ["1\t2\r\n3\t4\r\n", [[1, 2], [3, 4]]],
    'trailing tab is a blank last cell' => ["74.000\t", [[74_000, null]]],
    'single value' => ['84000', [[84_000]]],
    'Colombian block' => ["$ 42.000\t\$ 74.000\n53.000\t\n 1.250.000 \tN/A\n", [[42_000, 74_000], [53_000, null], [1_250_000, null]]],
]);

it('normalises a typed or pasted cost', function (string $value, ?int $expected) {
    expect(LabSheetPaste::cost($value))->toBe($expected)
        ->and(LabSheetPaste::parse($value))->toBe([[$expected]]);
})->with([
    'es-CO with cents and currency code' => ['42.000,00 COP', 42_000],
    'currency code first' => ['COP 1.250.000', 1_250_000],
    'peso sign' => ['$ 74.000', 74_000],
    'peso sign with a spreadsheet non-breaking space' => ["\$\u{00A0}42.000", 42_000],
    'peso sign glued to the code' => ['COP$ 42.000', 42_000],
    'no digit' => ['N/A', null],
    'negative' => ['-5', null],
    'text around a digit' => ['abc1', null],
]);
