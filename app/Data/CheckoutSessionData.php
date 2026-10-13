<?php

namespace App\Data;

use App\Exceptions\UnknownStripeOutcome;

final readonly class CheckoutSessionData
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public string $id, public ?string $url, public string $status, public string $paymentStatus,
        public int $amountCents, public string $currency, public bool $livemode,
        public ?string $paymentIntentId, public int $expiresAt, public array $metadata,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        if (($data['object'] ?? null) !== 'checkout.session' || ! is_string($data['id'] ?? null)
            || ! str_starts_with($data['id'], 'cs_') || ($data['mode'] ?? null) !== 'payment'
            || ! is_int($data['amount_total'] ?? null) || ! is_bool($data['livemode'] ?? null)
            || ! is_string($data['currency'] ?? null) || ! is_int($data['expires_at'] ?? null)
            || ! in_array($data['status'] ?? null, ['open', 'complete', 'expired'], true)
            || ! in_array($data['payment_status'] ?? null, ['paid', 'unpaid', 'no_payment_required'], true)
            || ! is_array($data['metadata'] ?? null)) {
            throw new UnknownStripeOutcome('Stripe returned an incomplete session.');
        }
        $url = $data['url'] ?? null;
        if ($url !== null && (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_HOST) !== 'checkout.stripe.com')) {
            throw new UnknownStripeOutcome('Stripe returned an invalid checkout URL.');
        }
        $intent = $data['payment_intent'] ?? null;
        if (is_array($intent)) {
            $intent = $intent['id'] ?? null;
        }
        if ($intent !== null && (! is_string($intent) || ! str_starts_with($intent, 'pi_'))) {
            throw new UnknownStripeOutcome('Stripe returned an invalid payment reference.');
        }

        return new self($data['id'], $url, $data['status'], $data['payment_status'], $data['amount_total'],
            strtoupper($data['currency']), $data['livemode'], $intent, $data['expires_at'], $data['metadata']);
    }
}
