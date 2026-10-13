<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PriceTier;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => (string) Str::uuid(), 'user_id' => null, 'status' => OrderStatus::Pending,
            'contact_name' => fake()->name(), 'contact_email' => fake()->safeEmail(),
            'phone_original' => '+52 614 123 4567', 'phone_canonical' => '+526141234567',
            'tier' => PriceTier::Public, 'currency' => 'MXN',
            'subtotal_cents' => 130000, 'discount_cents' => 0, 'base_cents' => 130000,
            'tax_cents' => 20800, 'total_cents' => 150800, 'settings_version' => 1,
            'fingerprint' => hash('sha256', (string) Str::uuid()),
        ];
    }
}
