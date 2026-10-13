<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(), 'reference' => (string) Str::uuid(),
            'submission_key' => (string) Str::uuid(), 'confirmation_hash' => hash('sha256', (string) Str::uuid()),
            'idempotency_key' => (string) Str::uuid(), 'status' => PaymentAttemptStatus::Creating,
            'amount_cents' => fn (array $attributes): int => Order::findOrFail($attributes['order_id'])->total_cents,
            'currency' => fn (array $attributes): string => Order::findOrFail($attributes['order_id'])->currency,
        ];
    }
}
