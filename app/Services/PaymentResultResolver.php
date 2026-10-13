<?php

namespace App\Services;

use App\Data\VerifiedPaymentResult;
use App\Exceptions\UnknownStripeOutcome;
use App\Models\StripeEvent;

class PaymentResultResolver
{
    public function __construct(private StripeGateway $gateway) {}

    public function resolve(StripeEvent $event): VerifiedPaymentResult
    {
        $this->gateway->assertOutsideTransaction();
        $payload = $event->payload;
        $object = $payload['data']['object'] ?? [];
        $id = $object['id'] ?? null;
        if (! is_string($id) || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $id)) {
            throw new UnknownStripeOutcome('Event has no usable reference.');
        }
        $account = $this->gateway->remote('GET', 'account');
        $accountId = $account['id'] ?? null;
        if (! is_string($accountId)) {
            throw new UnknownStripeOutcome('Stripe account identity unavailable.');
        }
        $problems = [];
        if ($accountId !== config('services.stripe.account_id') || (isset($payload['account']) && $payload['account'] !== $accountId)) {
            $problems[] = 'account_mismatch';
        }
        if (($payload['api_version'] ?? null) !== config('services.stripe.api_version')) {
            $problems[] = 'version_mismatch';
        }
        if (($payload['livemode'] ?? null) !== (bool) config('services.stripe.livemode')) {
            $problems[] = 'mode_mismatch';
        }
        $session = null;
        $intent = null;
        $charge = null;
        $refunds = [];
        $dispute = null;
        $type = $event->type;
        if (str_starts_with($type, 'checkout.session.')) {
            $session = $this->gateway->retrieveCheckout($id);
            if ($session->id !== $id) {
                $problems[] = 'session_mismatch';
            }
            if ($session->paymentIntentId !== null) {
                $intent = $this->gateway->remote('GET', 'payment_intents/'.$this->id($session->paymentIntentId, 'pi_'));
            }
        } elseif (str_starts_with($type, 'payment_intent.')) {
            $intent = $this->gateway->remote('GET', 'payment_intents/'.$this->id($id, 'pi_'));
            if (($intent['id'] ?? null) !== $id) {
                $problems[] = 'intent_mismatch';
            }
        } elseif (str_starts_with($type, 'refund.')) {
            $refund = $this->gateway->remote('GET', 'refunds/'.$this->id($id, 're_'));
            if (($refund['id'] ?? null) !== $id) {
                throw new UnknownStripeOutcome('Refund identity mismatch.');
            }
            $charge = $this->gateway->remote('GET', 'charges/'.$this->id($refund['charge'] ?? '', 'ch_'));
            $refunds = [$refund];
        } elseif (str_starts_with($type, 'charge.dispute.')) {
            $dispute = $this->gateway->remote('GET', 'disputes/'.$this->id($id, 'dp_'));
            if (($dispute['id'] ?? null) !== $id) {
                throw new UnknownStripeOutcome('Dispute identity mismatch.');
            }
            $charge = $this->gateway->remote('GET', 'charges/'.$this->id($dispute['charge'] ?? '', 'ch_'));
        } elseif (str_starts_with($type, 'charge.')) {
            $charge = $this->gateway->remote('GET', 'charges/'.$this->id($id, 'ch_'));
            if (($charge['id'] ?? null) !== $id) {
                $problems[] = 'charge_mismatch';
            }
            if ($type === 'charge.refunded') {
                $parameters = ['charge' => $id, 'limit' => 100];
                for ($page = 0; $page < 10; $page++) {
                    $list = $this->gateway->remote('GET', 'refunds', $parameters);
                    if (! is_array($list['data'] ?? null) || ! is_bool($list['has_more'] ?? null)) {
                        throw new UnknownStripeOutcome('Refund list incomplete.');
                    }
                    $refunds = array_merge($refunds, $list['data']);
                    if (! $list['has_more']) {
                        break;
                    }
                    $last = end($list['data']);
                    $parameters['starting_after'] = $this->id($last['id'] ?? '', 're_');
                    if ($page === 9) {
                        throw new UnknownStripeOutcome('Refund list exceeded bounded pages.');
                    }
                }
            }
        } else {
            throw new UnknownStripeOutcome('Unsupported financial event.');
        }
        if ($charge !== null && $intent === null) {
            $intent = $this->gateway->remote('GET', 'payment_intents/'.$this->id($charge['payment_intent'] ?? '', 'pi_'));
        }
        if ($intent !== null) {
            if (($intent['object'] ?? null) !== 'payment_intent' || ! is_string($intent['id'] ?? null) || ! is_array($intent['metadata'] ?? null)
                || ! is_int($intent['amount_received'] ?? null) || ! is_string($intent['currency'] ?? null) || ! is_bool($intent['livemode'] ?? null)) {
                throw new UnknownStripeOutcome('PaymentIntent evidence incomplete.');
            }
            if ($session !== null && $session->paymentIntentId !== $intent['id']) {
                $problems[] = 'intent_mismatch';
            }
            if ($charge === null && ($intent['latest_charge'] ?? null) !== null) {
                $chargeId = is_array($intent['latest_charge']) ? ($intent['latest_charge']['id'] ?? '') : $intent['latest_charge'];
                $charge = $this->gateway->remote('GET', 'charges/'.$this->id($chargeId, 'ch_'));
                if (($charge['id'] ?? null) !== $chargeId) {
                    $problems[] = 'charge_mismatch';
                }
            }
        }
        $metadata = $session?->metadata ?? ($intent['metadata'] ?? []);
        if (! is_string($metadata['attempt_reference'] ?? null) || ! is_string($metadata['order_reference'] ?? null)) {
            throw new UnknownStripeOutcome('No local payment reference.');
        }
        if ($intent !== null && $session !== null && $intent['metadata'] !== $session->metadata) {
            $problems[] = 'metadata_mismatch';
        }
        $amount = $session?->amountCents ?? ($intent['amount_received'] ?? 0);
        $currency = $session?->currency ?? strtoupper($intent['currency'] ?? '');
        $mode = $session?->livemode ?? ($intent['livemode'] ?? false);
        $state = $session?->status === 'expired' && $session->paymentStatus === 'unpaid' ? 'expired' : 'pending';
        if ($charge !== null) {
            if (($charge['object'] ?? null) !== 'charge' || ! is_string($charge['id'] ?? null) || ! is_int($charge['amount'] ?? null)
                || ! is_string($charge['currency'] ?? null) || ! is_bool($charge['livemode'] ?? null) || ! is_int($charge['created'] ?? null)) {
                throw new UnknownStripeOutcome('Charge evidence incomplete.');
            }
            if ($charge['payment_intent'] !== $intent['id']) {
                $problems[] = 'intent_mismatch';
            }
            if ($charge['amount'] !== $intent['amount_received'] || ($session !== null && $charge['amount'] !== $session->amountCents)) {
                $problems[] = 'amount_mismatch';
            }
            if (strtoupper($charge['currency']) !== $currency || strtoupper($intent['currency']) !== $currency) {
                $problems[] = 'currency_mismatch';
            }
            if ($charge['livemode'] !== $mode || $intent['livemode'] !== $mode) {
                $problems[] = 'mode_mismatch';
            }
            $amount = $charge['amount'];
            $currency = strtoupper($charge['currency']);
            $mode = $charge['livemode'];
            if (($charge['paid'] ?? false) === true && ($charge['captured'] ?? false) === true && ($charge['status'] ?? null) === 'succeeded'
                && $intent['status'] === 'succeeded') {
                $state = 'paid';
            }
        }
        if ($session !== null && $session->paymentStatus !== 'paid' && $state === 'paid') {
            $problems[] = 'session_payment_mismatch';
        }
        $normalRefunds = [];
        foreach ($refunds as $refund) {
            if (($refund['object'] ?? null) !== 'refund' || ! is_string($refund['id'] ?? null) || ! is_int($refund['amount'] ?? null)
                || ! is_int($refund['created'] ?? null) || ! in_array($refund['status'] ?? null, ['pending', 'requires_action', 'succeeded', 'failed', 'canceled'], true)
                || ($refund['charge'] ?? null) !== ($charge['id'] ?? null) || strtoupper($refund['currency'] ?? '') !== $currency) {
                throw new UnknownStripeOutcome('Refund evidence incomplete.');
            }
            $normalRefunds[] = ['id' => $refund['id'], 'amount' => $refund['amount'], 'status' => $refund['status'], 'created' => $refund['created']];
        }

        return new VerifiedPaymentResult($metadata['attempt_reference'], $metadata['order_reference'], 'stripe_api', $event->stripe_event_id,
            $accountId, $mode, $currency, $amount, $state, $session?->id, $intent['id'] ?? null, $charge['id'] ?? null,
            $charge['created'] ?? $payload['created'] ?? now()->timestamp, array_values(array_unique($problems)), $normalRefunds, $dispute['id'] ?? null, $dispute['status'] ?? null);
    }

    private function id(mixed $value, string $prefix): string
    {
        if (! is_string($value) || ! str_starts_with($value, $prefix) || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $value)) {
            throw new UnknownStripeOutcome('Invalid Stripe reference.');
        }

        return $value;
    }
}
