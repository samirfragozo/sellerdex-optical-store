<?php

namespace App\Filament\Resources\Prescriptions\Concerns;

trait InteractsWithPrescriptionSigns
{
    /**
     * Signed diopter columns that use a +/- sign toggle in the form.
     *
     * @return list<string>
     */
    protected function signedDiopterFields(): array
    {
        return ['od_sphere', 'os_sphere', 'od_cylinder', 'os_cylinder'];
    }

    /**
     * Always-positive diopter columns (the Add power).
     *
     * @return list<string>
     */
    protected function positiveDiopterFields(): array
    {
        return ['od_add', 'os_add'];
    }

    /**
     * Split stored signed values into sign + magnitude helper fields for the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function splitDiopterSigns(array $data): array
    {
        foreach ([...$this->signedDiopterFields(), ...$this->positiveDiopterFields()] as $field) {
            $value = $data[$field] ?? null;
            $data["{$field}_sign"] = (is_string($value) && str_starts_with($value, '-')) ? '-' : '+';
            $data["{$field}_num"] = ($value === null || $value === '') ? null : ltrim((string) $value, '+-');
        }

        return $data;
    }

    /**
     * Combine sign + magnitude helpers back into the stored signed columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function combineDiopterSigns(array $data): array
    {
        foreach ($this->signedDiopterFields() as $field) {
            $sign = (($data["{$field}_sign"] ?? '+') === '-') ? '-' : '+';
            $data[$field] = $this->buildSignedValue($data["{$field}_num"] ?? null, $sign);
        }

        foreach ($this->positiveDiopterFields() as $field) {
            $data[$field] = $this->buildSignedValue($data["{$field}_num"] ?? null, '+');
        }

        foreach ([...$this->signedDiopterFields(), ...$this->positiveDiopterFields()] as $field) {
            unset($data["{$field}_sign"], $data["{$field}_num"]);
        }

        return $data;
    }

    private function buildSignedValue(mixed $magnitude, string $sign): ?string
    {
        if ($magnitude === null || $magnitude === '') {
            return null;
        }

        return $sign.ltrim((string) $magnitude, '+-');
    }
}
