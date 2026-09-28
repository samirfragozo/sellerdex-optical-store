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
            $fail(__('app.validation.diopter.invalid'));

            return;
        }

        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        if (! preg_match('/^[+-]?\d+(\.\d+)?$/', $normalized)) {
            $fail(__('app.validation.diopter.invalid_format'));

            return;
        }

        $number = (float) $normalized;

        if ($number < $this->min || $number > $this->max) {
            $fail(__('app.validation.diopter.out_of_range', [
                'min' => $this->format($this->min),
                'max' => $this->format($this->max),
            ]));

            return;
        }

        // Multiple-of-step check: number / step must be a whole number.
        $ratio = $number / $this->step;
        if (abs($ratio - round($ratio)) > 1e-6) {
            $fail(__('app.validation.diopter.step', ['step' => $this->format($this->step)]));
        }
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
