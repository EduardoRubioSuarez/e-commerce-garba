<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponUse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CouponUse> */
class CouponUseFactory extends Factory
{
    public function definition(): array
    {
        return ['coupon_id' => Coupon::factory(), 'phone_canonical' => '+526141234567', 'status' => 'free'];
    }
}
