<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO########')),
            'kind' => 'percentage', 'value' => 1000, 'currency' => 'MXN',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
            'minimum_base_minor' => 0, 'max_uses' => 50,
            'applies_to_installers' => false, 'applies_to_manual_prices' => false,
            'scope' => 'all', 'is_active' => true,
        ];
    }
}
