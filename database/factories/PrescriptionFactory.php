<?php

namespace Database\Factories;

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
            'od_axis' => 90,
            'os_sphere' => '0',
            'os_cylinder' => '-2.75',
            'os_axis' => 180,
            'prescriber_name' => fake()->name(),
            'filters' => ['Fotocromático', 'Antirreflejo Blue'],
            'diagnosis' => 'Paciente refiere mala visión en VL y VP',
        ];
    }
}
