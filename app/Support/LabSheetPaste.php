<?php

namespace App\Support;

/**
 * Turns lab prices as people write them ("$ 42.000,00", "COP 1.250.000") into
 * integer costs, one cell or a block copied from a spreadsheet (rows on new
 * lines, cells on tabs). A cell with no digit is blank.
 */
final class LabSheetPaste
{
    /** @return list<list<?int>> */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $text);

        if (count($lines) > 1 && end($lines) === '') {
            array_pop($lines);
        }

        return array_map(
            fn (string $line): array => array_map(self::cost(...), explode("\t", $line)),
            $lines,
        );
    }

    /** Keep digits and separators, drop a trailing decimal part, then the separators. */
    public static function cost(string $value): ?int
    {
        // Thousands groups are always 3 digits, so a trailing 1–2 digit group is cents to drop.
        $number = preg_replace('/[.,]\d{1,2}$/', '', preg_replace('/[^\d.,]/', '', $value));
        $digits = preg_replace('/\D/', '', $number);

        return $digits === '' ? null : (int) $digits;
    }
}
