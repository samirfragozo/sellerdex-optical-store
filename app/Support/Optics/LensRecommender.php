<?php

namespace App\Support\Optics;

class LensRecommender
{
    /**
     * Recommend lens dimensions from a prescription's refraction.
     *
     * @param  array<string,mixed>  $prescription
     * @return array{design:string,process:string,material:string,filter:string,signals:array{has_add:bool,max_power:float}}
     */
    public function recommend(array $prescription): array
    {
        $hasAdd = $this->hasAddition($prescription);
        $maxPower = $this->maxPower($prescription);

        $design = $hasAdd ? 'Progresivo' : 'Monofocal';
        $process = ($hasAdd || $maxPower >= 4.0) ? 'Digital' : 'Terminado';

        return [
            'design' => $design,
            'process' => $process,
            'material' => $this->materialForPower($maxPower),
            'filter' => $this->filterFor($prescription),
            'signals' => ['has_add' => $hasAdd, 'max_power' => $maxPower],
        ];
    }

    /**
     * Soft warnings when a chosen configuration is incongruent with the formula.
     *
     * @param  array<string,mixed>  $prescription
     * @param  array{design?:?string,material?:?string}  $chosen
     * @return array<int,string>
     */
    public function warningsFor(array $prescription, array $chosen): array
    {
        $warnings = [];
        $hasAdd = $this->hasAddition($prescription);
        $maxPower = $this->maxPower($prescription);
        $design = $chosen['design'] ?? null;
        $material = $chosen['material'] ?? null;

        if ($design === 'Monofocal' && $hasAdd) {
            $warnings[] = 'La fórmula tiene adición; suele requerir un lente bifocal o progresivo.';
        }

        if (in_array($design, ['Bifocal', 'Progresivo'], true) && ! $hasAdd) {
            $warnings[] = 'La fórmula no tiene adición; confirma que el lente deba ser multifocal.';
        }

        if (in_array($material, ['CR-39', 'Material 1.56'], true) && $maxPower >= 4.0) {
            $warnings[] = 'Potencia alta: un material de alto índice da un lente más delgado y liviano.';
        }

        return $warnings;
    }

    /** @param array<string,mixed> $rx */
    private function hasAddition(array $rx): bool
    {
        return $this->num($rx['od_add'] ?? null) > 0 || $this->num($rx['os_add'] ?? null) > 0;
    }

    /** Greatest |sphere| + |cylinder| across both eyes. @param array<string,mixed> $rx */
    private function maxPower(array $rx): float
    {
        $od = abs($this->num($rx['od_sphere'] ?? null)) + abs($this->num($rx['od_cylinder'] ?? null));
        $os = abs($this->num($rx['os_sphere'] ?? null)) + abs($this->num($rx['os_cylinder'] ?? null));

        return max($od, $os);
    }

    private function materialForPower(float $power): string
    {
        return match (true) {
            $power >= 6.0 => 'Material 1.74',
            $power >= 4.0 => 'Material 1.67',
            $power >= 2.0 => 'Policarbonato',
            default => 'Material 1.56',
        };
    }

    /** @param array<string,mixed> $rx */
    private function filterFor(array $rx): string
    {
        $filters = $rx['filters'] ?? [];
        $haystack = is_array($filters) ? strtolower(implode(' ', $filters)) : strtolower((string) $filters);

        return match (true) {
            str_contains($haystack, 'foto') => 'Foto Blue Cut',
            str_contains($haystack, 'blue') => 'Blue Cut',
            default => 'Sin Filtro',
        };
    }

    private function num(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
