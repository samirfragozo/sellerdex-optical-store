<?php

namespace Database\Factories;

use App\Enums\LensType;
use App\Models\Customer;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by' => null,
            'exam_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'od_sphere' => '-0.25',
            'od_cylinder' => '-2.00',
            'od_axis' => '0',
            'os_sphere' => 'N',
            'os_cylinder' => '-2.75',
            'os_axis' => '0',
            'lens_type' => LensType::ExtendedRange->value,
            'filters' => ['Fotocromático', 'Antirreflejo Blue'],
            'usage' => 'Prolongado',
            'control_period' => 'Anual',
            'diagnosis' => 'Paciente refiere mala visión en VL y VP',
            'drops' => null,
            'lensometry' => null,
        ];
    }
}
