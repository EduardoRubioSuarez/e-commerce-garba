<?php

namespace Database\Factories;

use App\Models\StripeEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StripeEvent> */
class StripeEventFactory extends Factory
{
    public function definition(): array
    {
        $payload = ['id' => 'evt_'.fake()->uuid(), 'object' => 'event', 'type' => 'customer.created', 'created' => now()->timestamp,
            'api_version' => '2026-08-26.dahlia', 'livemode' => false, 'data' => ['object' => ['id' => 'cus_fixture']]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return ['stripe_event_id' => $payload['id'], 'type' => $payload['type'], 'status' => 'pending', 'payload' => $payload,
            'raw_body' => $body, 'body_hash' => hash('sha256', $body), 'received_at' => now()];
    }
}
