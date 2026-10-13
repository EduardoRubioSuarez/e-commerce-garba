<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PriceTier;
use App\Models\PriceOverride;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceOverride>
 */
class PriceOverrideFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PriceOverride>
     */
    protected $model = PriceOverride::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'tier' => PriceTier::Public,
            'base_price_cents' => fake()->numberBetween(1000, 100000),
        ];
    }
}
