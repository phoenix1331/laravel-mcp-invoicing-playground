<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'position' => 0,
        ];
    }
}
