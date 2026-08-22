<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'issue_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'currency' => 'GBP',
            'notes' => fake()->optional()->sentence(),
            'subtotal' => 0,
            'tax_rate' => 20,
            'tax_total' => 0,
            'total' => 0,
        ];
    }
}
