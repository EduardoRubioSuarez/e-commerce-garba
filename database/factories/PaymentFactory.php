<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'payment_attempt_id' => fn (array $a) => PaymentAttempt::factory()->create(['order_id' => $a['order_id']])->id,
            'stripe_charge_id' => 'ch_'.fake()->uuid(), 'stripe_payment_intent_id' => 'pi_'.fake()->uuid(), 'account_id' => 'acct_fixture', 'livemode' => false,
            'amount_cents' => 150800, 'currency' => 'MXN', 'source' => 'fixture', 'evidence_id' => 'evt_fixture', 'paid_at' => now()];
    }
}
