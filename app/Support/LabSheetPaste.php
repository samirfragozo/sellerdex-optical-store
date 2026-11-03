<?php

namespace App\Support;

/**
 * Turns a block copied from a spreadsheet (rows on new lines, cells on tabs)
 * into lab costs. Colombian prices use "." as thousands separator and have no
 * cents, so every non-digit is dropped; a cell with no digit is blank.
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
                $digits = preg_replace('/\D/', '', $cell);

                return $digits === '' ? null : (int) $digits;
            }, explode("\t", $line)),
            $lines,
        );
    }
}
