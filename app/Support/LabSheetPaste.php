<?php

namespace App\Support;

/**
 * Turns a block copied from a spreadsheet (rows on new lines, cells on tabs)
 * into lab costs. A trailing decimal part (",00" in es-CO currency display) is
 * dropped, then every non-digit; a cell with no digit is blank.
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
            fn (string $line): array => array_map(function (string $cell): ?int {
                // Thousands groups are always 3 digits, so a trailing 1–2 digit group is cents to drop.
                $digits = preg_replace('/\D/', '', preg_replace('/[.,]\d{1,2}\s*$/', '', $cell));

                return $digits === '' ? null : (int) $digits;
            }, explode("\t", $line)),
            $lines,
        );
    }
}
