<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Models\StripeEvent;
use App\Services\PaymentResultApplier;
use App\Services\PaymentResultResolver;
use App\Services\StripeWebhookVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookVerifier $verifier): Response
    {
        $body = $request->getContent();
        try {
            $payload = $verifier->verify($body, (string) $request->header('Stripe-Signature'));
        } catch (InvalidArgumentException) {
            return response('Invalid webhook.', 400);
        }
        try {
            $event = StripeEvent::query()->where('stripe_event_id', $payload['id'])->first();
            if ($event === null) {
                $event = new StripeEvent;
                $event->forceFill(['stripe_event_id' => $payload['id'], 'type' => $payload['type'], 'status' => StripeEvent::supports($payload['type']) ? 'pending' : 'ignored',
                    'payload' => $payload, 'raw_body' => $body, 'body_hash' => hash('sha256', $body), 'received_at' => now()]);
                if (! $event->save()) {
                    throw new RuntimeException('Event was not persisted.');
                }
            } elseif (! hash_equals($event->body_hash, hash('sha256', $body))) {
                return response('Conflicting event.', 409);
            }
        } catch (UniqueConstraintViolationException) {
            $existing = StripeEvent::query()->where('stripe_event_id', $payload['id'])->first();
            if ($existing === null || ! hash_equals($existing->body_hash, hash('sha256', $body))) {
                return response('Event unavailable.', 503);
            }
            $event = $existing;
        } catch (Throwable) {
            return response('Event unavailable.', 503);
        }
        if ($event->status === 'pending') {
            try {
                $result = app(PaymentResultResolver::class)->resolve($event);
                $attempt = PaymentAttempt::query()->where('reference', $result->attemptReference)->firstOrFail();
                app(PaymentResultApplier::class)->apply($attempt, $result, $event);
            } catch (Throwable) {
                $event->last_error = 'processing_pending';
                $event->save();
            }
        }

        return response('Accepted.', 200);
    }
}
