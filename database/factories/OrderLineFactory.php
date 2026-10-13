<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory()->published()->state(['base_cost_cents' => 100000]),
            'name' => fn (array $attributes): string => Product::findOrFail($attributes['product_id'])->name,
            'quantity' => 1, 'unit_base_cents' => 130000, 'price_source' => 'automatic',
            'base_cents' => 130000, 'discount_cents' => 0, 'discounted_base_cents' => 130000,
            'tax_basis_points' => 1600, 'tax_cents' => 20800, 'total_cents' => 150800,
        ];
    }
}
