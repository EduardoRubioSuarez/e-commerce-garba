<?php

namespace App\Services;

use InvalidArgumentException;
use JsonException;

class StripeWebhookVerifier
{
    /** @return array{id:string,object:string,type:string,created:int,livemode:bool,api_version:string,data:array{object:array<string,mixed>},account?:string} */
    public function verify(string $body, string $signatureHeader): array
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('Webhook verification unavailable.');
        }
        $timestamps = [];
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't' && preg_match('/\A[0-9]{1,12}\z/', $pair[1])) {
                $timestamps[] = (int) $pair[1];
            }
            if ($pair[0] === 'v1' && preg_match('/\A[a-f0-9]{64}\z/', $pair[1])) {
                $signatures[] = $pair[1];
            }
        }
        if (count($timestamps) !== 1 || abs(now()->timestamp - $timestamps[0]) > 300 || strlen($body) > 1048576) {
            throw new InvalidArgumentException('Invalid webhook signature.');
        }
        $expected = hash_hmac('sha256', $timestamps[0].'.'.$body, $secret);
        $matched = false;
        foreach ($signatures as $signature) {
            $matched = hash_equals($expected, $signature) || $matched;
        }
        if (! $matched) {
            throw new InvalidArgumentException('Invalid webhook signature.');
        }
        try {
            $event = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid webhook payload.');
        }
        if (! is_array($event) || ($event['object'] ?? null) !== 'event' || ! is_string($event['id'] ?? null)
            || ! preg_match('/\Aevt_[a-zA-Z0-9_]{1,240}\z/', $event['id']) || ! is_string($event['type'] ?? null)
            || ! is_int($event['created'] ?? null) || ! is_bool($event['livemode'] ?? null)
            || ! is_string($event['api_version'] ?? null) || ! is_array($event['data']['object'] ?? null)) {
            throw new InvalidArgumentException('Invalid webhook payload.');
        }

        return $event;
    }
}
