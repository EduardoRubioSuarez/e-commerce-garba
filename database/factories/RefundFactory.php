<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Refund> */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return ['payment_id' => Payment::factory(), 'stripe_refund_id' => 're_'.fake()->uuid(), 'amount_cents' => 150800, 'currency' => 'MXN', 'status' => 'succeeded', 'confirmed_at' => now()];
    }
}
