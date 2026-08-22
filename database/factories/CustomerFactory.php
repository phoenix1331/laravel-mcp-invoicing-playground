<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organisation;
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
            'organisation_id' => Organisation::factory(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
        ];
    }
}
