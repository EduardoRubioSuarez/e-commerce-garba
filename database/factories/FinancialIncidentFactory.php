<?php

namespace Database\Factories;

use App\Models\FinancialIncident;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialIncident> */
class FinancialIncidentFactory extends Factory
{
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'incident_key' => fake()->uuid(), 'kind' => 'review', 'reason' => 'fixture'];
    }
}
