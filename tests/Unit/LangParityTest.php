<?php

/**
 * @param  array<string, mixed>  $translations
 * @return list<string>
 */
function flattenLangKeys(array $translations, string $prefix = ''): array
{
    $keys = [];
    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        $keys = is_array($value) ? [...$keys, ...flattenLangKeys($value, $path)] : [...$keys, $path];
    }

    return $keys;
}

it('defines the same translation keys in Spanish and English', function (string $file) {
    $spanish = flattenLangKeys(require dirname(__DIR__, 2)."/lang/es/{$file}.php");
    $english = flattenLangKeys(require dirname(__DIR__, 2)."/lang/en/{$file}.php");

    expect(array_values(array_diff($spanish, $english)))->toBe([], 'Missing in lang/en')
        ->and(array_values(array_diff($english, $spanish)))->toBe([], 'Missing in lang/es');
})->with(['app', 'auth', 'settings']);
