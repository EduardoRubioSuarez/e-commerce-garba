<?php

namespace App\Services;

use App\Data\CheckoutSessionData;
use App\Data\VerifiedPaymentResult;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\DefinitiveStripeFailure;
use App\Exceptions\UnknownStripeOutcome;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class StripeGateway
{
    public function createCheckout(PaymentAttempt $attempt): CheckoutSessionData
    {
        $this->assertOutsideTransaction();
        $this->request();
        $fresh = $attempt->fresh();
        $firstRequest = $fresh->first_sent_at === null && $fresh->checkout_parameters === null;
        if ($fresh->order->user_id !== null && ! $fresh->order->user?->isActive()) {
            $found = $fresh->stripe_session_id !== null
                ? $this->retrieveCheckout($fresh->stripe_session_id)
                : ($fresh->first_sent_at !== null ? $this->findCheckout($fresh) : null);
            if ($found === null) {
                throw new UnknownStripeOutcome('Revoked access cannot create a new Checkout.');
            }
            $this->validateSession($fresh, $found);
            $this->remember($fresh, $found);

            return $found;
        }
        if ($fresh->stripe_session_id === null && $fresh->first_sent_at !== null
            && $fresh->first_sent_at->addHours((int) config('services.stripe.safe_retry_hours', 23))->lte(now())) {
            $this->mark($fresh, PaymentAttemptStatus::Unknown);
            $found = $this->findCheckout($fresh);
            if ($found === null) {
                throw new UnknownStripeOutcome('The safe retry window has elapsed.');
            }
            $this->remember($fresh, $found);

            return $found;
        }
        $attempt = DB::transaction(function () use ($attempt, &$firstRequest): PaymentAttempt {
            Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $current = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $firstRequest = $current->checkout_parameters === null && $current->first_sent_at === null;
            if ($current->checkout_parameters === null) {
                $order = $current->order;
                $metadata = ['order_reference' => $order->reference, 'attempt_reference' => $current->reference];
                $current->first_sent_at = now();
                $current->expires_at = now()->addMinutes(31);
                $current->checkout_parameters = [
                    'mode' => 'payment', 'ui_mode' => 'embedded_page', 'redirect_on_completion' => 'never',
                    'payment_method_types' => ['card'],
                    'line_items' => [['quantity' => 1, 'price_data' => [
                        'currency' => strtolower($current->currency), 'unit_amount' => $current->amount_cents,
                        'product_data' => ['name' => 'Pedido '.$order->reference],
                    ]]],
                    'automatic_tax' => ['enabled' => 'false'], 'allow_promotion_codes' => 'false',
                    'expires_at' => $current->expires_at->timestamp,
                    'client_reference_id' => $current->reference, 'metadata' => $metadata,
                    'payment_intent_data' => ['metadata' => $metadata],
                ];
                $this->save($current);
            }

            return $current;
        }, 3);
        if ($attempt->status === PaymentAttemptStatus::Open && $attempt->stripe_session_id !== null && $attempt->checkout_url !== null && $attempt->expires_at->isFuture()) {
            return new CheckoutSessionData($attempt->stripe_session_id, $attempt->checkout_url, 'open', 'unpaid',
                $attempt->amount_cents, $attempt->currency, (bool) config('services.stripe.livemode'), $attempt->stripe_payment_intent_id,
                $attempt->expires_at->timestamp, ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference]);
        }
        if ($attempt->stripe_session_id !== null) {
            $session = $this->retrieveCheckout($attempt->stripe_session_id);
            $this->validateSession($attempt, $session);
            $this->remember($attempt, $session);

            return $session;
        }
        if (in_array($attempt->status, [PaymentAttemptStatus::Paid, PaymentAttemptStatus::Failed, PaymentAttemptStatus::Expired], true)) {
            throw new DefinitiveStripeFailure('This payment attempt cannot create Checkout.');
        }
        try {
            $data = $this->request()->withHeaders(['Idempotency-Key' => $attempt->idempotency_key])
                ->post('https://api.stripe.com/v1/checkout/sessions', $attempt->checkout_parameters);
            if (! $data->successful()) {
                if (in_array($data->status(), [400, 401, 403, 404, 422], true) && $attempt->status !== PaymentAttemptStatus::Unknown) {
                    throw new DefinitiveStripeFailure('Stripe rejected Checkout.');
                }
                throw new UnknownStripeOutcome('Checkout outcome is not confirmed.');
            }
            $session = CheckoutSessionData::fromArray($data->json());
            $this->validateSession($attempt, $session);
            $this->remember($attempt, $session);

            return $session;
        } catch (ConnectionException|UnknownStripeOutcome $e) {
            $this->mark($attempt, PaymentAttemptStatus::Unknown);
            throw new UnknownStripeOutcome('Checkout outcome is not confirmed.');
        } catch (DefinitiveStripeFailure $e) {
            if ($attempt->order->coupon_id !== null) {
                if (! $firstRequest) {
                    $this->mark($attempt, PaymentAttemptStatus::Unknown);
                    throw new UnknownStripeOutcome('A prior remote request requires reconciliation before release.');
                }
                app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult($attempt->reference, $attempt->order->reference,
                    'stripe_create_rejection', 'rejection:'.$attempt->reference, config('services.stripe.account_id'), (bool) config('services.stripe.livemode'),
                    $attempt->currency, $attempt->amount_cents, 'failed'));
                throw $e;
            }
            $this->mark($attempt, PaymentAttemptStatus::Failed);
            throw $e;
        }
    }

    public function assertOutsideTransaction(): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Stripe HTTP must run outside transactions.');
        }
    }

    public function retrieveCheckout(string $sessionId): CheckoutSessionData
    {
        return CheckoutSessionData::fromArray($this->remote('GET', 'checkout/sessions/'.$this->remoteId($sessionId, 'cs_')));
    }

    public function embeddedClientSecret(PaymentAttempt $attempt): ?string
    {
        if ($attempt->stripe_session_id === null || ($attempt->checkout_parameters['ui_mode'] ?? null) !== 'embedded_page') {
            throw new UnknownStripeOutcome('Embedded session is not available.');
        }
        $data = $this->remote('GET', 'checkout/sessions/'.$this->remoteId($attempt->stripe_session_id, 'cs_'));
        $session = CheckoutSessionData::fromArray($data);
        $this->validateSession($attempt, $session);
        if (($data['ui_mode'] ?? null) !== 'embedded_page') {
            throw new UnknownStripeOutcome('Unexpected Checkout interface.');
        }
        if ($session->status !== 'open' || $session->paymentStatus !== 'unpaid' || $session->expiresAt <= now()->timestamp) {
            return null;
        }
        $secret = $data['client_secret'] ?? null;
        if (! is_string($secret) || ! str_starts_with($secret, $session->id.'_secret_') || strlen($secret) > 512) {
            throw new UnknownStripeOutcome('Embedded session credentials unavailable.');
        }

        return $secret;
    }

    public function expireCheckout(string $sessionId): CheckoutSessionData
    {
        try {
            return CheckoutSessionData::fromArray($this->remote('POST', 'checkout/sessions/'.$this->remoteId($sessionId, 'cs_').'/expire'));
        } catch (UnknownStripeOutcome) {
            return $this->retrieveCheckout($sessionId);
        }
    }

    public function findCheckout(PaymentAttempt $attempt): ?CheckoutSessionData
    {
        $parameters = ['limit' => 100, 'created' => ['gte' => ($attempt->first_sent_at ?? $attempt->created_at)->subMinutes(5)->timestamp]];
        for ($page = 0; $page < 10; $page++) {
            $list = $this->remote('GET', 'checkout/sessions', $parameters);
            if (! is_array($list['data'] ?? null) || ! is_bool($list['has_more'] ?? null)) {
                throw new UnknownStripeOutcome('Checkout search was incomplete.');
            }
            $matches = [];
            foreach ($list['data'] as $data) {
                if (($data['metadata']['attempt_reference'] ?? null) === $attempt->reference) {
                    $session = CheckoutSessionData::fromArray($data);
                    $this->validateSession($attempt, $session);
                    $matches[] = $session;
                }
            }
            if (count($matches) > 1) {
                throw new UnknownStripeOutcome('Multiple sessions require review.');
            }
            if ($matches !== []) {
                return $matches[0];
            }
            if (! $list['has_more']) {
                return null;
            }
            $last = end($list['data']);
            if (! is_array($last) || ! is_string($last['id'] ?? null)) {
                throw new UnknownStripeOutcome('Checkout search was incomplete.');
            }
            $parameters['starting_after'] = $last['id'];
        }
        throw new UnknownStripeOutcome('Checkout search exceeded its bounded pages.');
    }

    /** @param array<string,mixed> $parameters
     * @return array<string,mixed>
     */
    public function remote(string $method, string $path, array $parameters = []): array
    {
        $this->assertOutsideTransaction();
        try {
            $response = $method === 'GET'
                ? $this->request()->get('https://api.stripe.com/v1/'.$path, $parameters)
                : $this->request()->post('https://api.stripe.com/v1/'.$path, $parameters);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new UnknownStripeOutcome('Stripe evidence is not available.');
            }

            return $response->json();
        } catch (ConnectionException) {
            throw new UnknownStripeOutcome('Stripe evidence is not available.');
        }
    }

    private function remoteId(string $id, string $prefix): string
    {
        if (! str_starts_with($id, $prefix) || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $id)) {
            throw new UnknownStripeOutcome('Invalid remote reference.');
        }

        return $id;
    }

    protected function request(): PendingRequest
    {
        $this->assertOutsideTransaction();
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || ! str_starts_with($secret, config('services.stripe.livemode') ? 'sk_live_' : 'sk_test_')
            || ! is_string(config('services.stripe.account_id')) || config('services.stripe.account_id') === '') {
            throw new UnknownStripeOutcome('Stripe configuration is not ready.');
        }

        $timeout = (float) config('services.stripe.timeout', 5);
        $deadline = config('services.stripe.command_deadline');
        if ($deadline !== null) {
            $remaining = (float) $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                throw new UnknownStripeOutcome('Recovery time budget exhausted.');
            }
            $timeout = min($timeout, $remaining);
        }

        return Http::asForm()->withBasicAuth($secret, '')->withHeaders(['Stripe-Version' => config('services.stripe.api_version')])
            ->connectTimeout(min((float) config('services.stripe.connect_timeout', 3), $timeout))->timeout($timeout);
    }

    public function validateSession(PaymentAttempt $attempt, CheckoutSessionData $session): void
    {
        if ($session->amountCents !== $attempt->amount_cents || $session->currency !== $attempt->currency
            || $session->livemode !== (bool) config('services.stripe.livemode')
            || ($session->metadata['attempt_reference'] ?? null) !== $attempt->reference
            || ($session->metadata['order_reference'] ?? null) !== $attempt->order->reference
            || ($attempt->stripe_session_id !== null && $attempt->stripe_session_id !== $session->id)) {
            throw new UnknownStripeOutcome('Checkout evidence does not match the attempt.');
        }
    }

    public function remember(PaymentAttempt $attempt, CheckoutSessionData $session): void
    {
        DB::transaction(function () use ($attempt, $session): void {
            Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $current = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $this->validateSession($current, $session);
            $current->stripe_session_id = $session->id;
            $current->checkout_url = $session->url;
            $current->expires_at = CarbonImmutable::createFromTimestampUTC($session->expiresAt);
            if ($current->status !== PaymentAttemptStatus::Paid) {
                $current->status = PaymentAttemptStatus::Open;
            }
            $this->save($current);
        }, 3);
    }

    private function mark(PaymentAttempt $attempt, PaymentAttemptStatus $status): void
    {
        DB::transaction(function () use ($attempt, $status): void {
            Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $current = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($current->status === PaymentAttemptStatus::Unknown && $status === PaymentAttemptStatus::Failed) {
                return;
            }
            if (! in_array($current->status, [PaymentAttemptStatus::Paid, PaymentAttemptStatus::Open, PaymentAttemptStatus::Expired], true)) {
                $current->status = $status;
                $current->last_error = $status->value;
                $this->save($current);
            }
        }, 3);
    }

    private function save(PaymentAttempt $attempt): void
    {
        if (! $attempt->save()) {
            throw new RuntimeException('Could not persist Checkout.');
        }
    }
}
