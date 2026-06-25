<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class Diopter implements ValidationRule
{
    public function __construct(
        private float $min,
        private float $max,
        private float $step = 0.25,
    ) {}

    /**
     * Run the validation rule. Accepts an optionally-signed decimal that must
     * sit within [min, max] and be a multiple of the step (default 0.25 D).
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Treat empty values as "not provided"; pair with the `nullable` rule.
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            $fail('El valor de :attribute no es válido.');

            return;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $normalized)) {
            $fail('El valor de :attribute no es válido. Usa solo números, por ejemplo 1.25.');

            return;
        }

        $number = (float) $normalized;

        if ($number < $this->min || $number > $this->max) {
            $fail(sprintf('El valor de :attribute debe estar entre %s y %s.', $this->format($this->min), $this->format($this->max)));

            return;
        }

        // Multiple-of-step check: number / step must be a whole number.
        $ratio = $number / $this->step;
        if (abs($ratio - round($ratio)) > 1e-6) {
            $fail(sprintf('El valor de :attribute debe ir en pasos de %s.', $this->format($this->step)));
        }
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
