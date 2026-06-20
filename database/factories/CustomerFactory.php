<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => fake()->randomElement(DocumentType::cases())->value,
            'id_number' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('3#########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-8 years')->format('Y-m-d'),
            'email' => fake()->optional()->safeEmail(),
            'notes' => null,
        ];
    }
}
