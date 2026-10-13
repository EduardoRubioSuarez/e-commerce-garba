<?php

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\Order;
use App\Models\OrderNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderNotification> */
class OrderNotificationFactory extends Factory
{
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'notification_key' => fake()->uuid(), 'type' => 'purchase', 'destination' => 'cliente@example.test', 'status' => NotificationStatus::Pending, 'requested_at' => now()];
    }
}
