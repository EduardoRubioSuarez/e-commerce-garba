<?php

namespace Database\Factories;

use App\Models\NotificationAttempt;
use App\Models\OrderNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NotificationAttempt> */
class NotificationAttemptFactory extends Factory
{
    public function definition(): array
    {
        return ['order_notification_id' => OrderNotification::factory(), 'claim_token' => (string) Str::uuid(), 'status' => 'prepared', 'started_at' => now()];
    }
}
