<?php

namespace Database\Factories;

use App\Models\CouponUse;
use App\Models\CouponUseHistory;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CouponUseHistory> */
class CouponUseHistoryFactory extends Factory
{
    public function definition(): array
    {
        return ['coupon_use_id' => CouponUse::factory(), 'from_status' => 'free', 'to_status' => 'reserved',
            'payment_attempt_id' => PaymentAttempt::factory(),
            'order_id' => fn (array $attributes): int => PaymentAttempt::findOrFail($attributes['payment_attempt_id'])->order_id];
    }
}
