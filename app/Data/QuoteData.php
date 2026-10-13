<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PriceTier;
use JsonSerializable;

final readonly class QuoteData implements JsonSerializable
{
    public string $fingerprint;

    /**
     * @param  list<QuoteLineData>  $lines
     */
    public function __construct(
        public array $lines,
        public PriceTier $tier,
        public string $currency,
        public int $subtotalCents,
        public int $discountCents,
        public int $baseCents,
        public int $taxCents,
        public int $totalCents,
        public int $settingsVersion,
        ?string $fingerprint = null,
        public ?array $couponSnapshot = null,
    ) {
        $this->fingerprint = $fingerprint ?? self::computeFingerprint(
            $this->lines,
            $this->tier,
            $this->currency,
            $this->subtotalCents,
            $this->discountCents,
            $this->baseCents,
            $this->taxCents,
            $this->totalCents,
            $this->settingsVersion,
            $this->couponSnapshot,
        );
    }

    /**
     * Compute a deterministic canonical SHA-256 fingerprint for the quote data.
     *
     * @param  list<QuoteLineData>  $lines
     */
    public static function computeFingerprint(
        array $lines,
        PriceTier $tier,
        string $currency,
        int $subtotalCents,
        int $discountCents,
        int $baseCents,
        int $taxCents,
        int $totalCents,
        int $settingsVersion,
        ?array $couponSnapshot = null,
    ): string {
        $payload = [
            'lines' => array_map(static fn (QuoteLineData $line): array => $line->toArray(), $lines),
            'tier' => $tier->value,
            'currency' => $currency,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'base_cents' => $baseCents,
            'tax_cents' => $taxCents,
            'total_cents' => $totalCents,
            'settings_version' => $settingsVersion,
        ];

        if ($couponSnapshot !== null) {
            $payload['coupon'] = $couponSnapshot;
        }

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     tier: string,
     *     currency: string,
     *     subtotal_cents: int,
     *     discount_cents: int,
     *     base_cents: int,
     *     tax_cents: int,
     *     total_cents: int,
     *     settings_version: int,
     *     fingerprint: string
     * }
     */
    public function toArray(): array
    {
        $data = [
            'lines' => array_map(static fn (QuoteLineData $line): array => $line->toArray(), $this->lines),
            'tier' => $this->tier->value,
            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotalCents,
            'discount_cents' => $this->discountCents,
            'base_cents' => $this->baseCents,
            'tax_cents' => $this->taxCents,
            'total_cents' => $this->totalCents,
            'settings_version' => $this->settingsVersion,
            'fingerprint' => $this->fingerprint,
        ];
        if ($this->couponSnapshot !== null) {
            $data['coupon'] = $this->couponSnapshot;
        }

        return $data;
    }

    /**
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     tier: string,
     *     currency: string,
     *     subtotal_cents: int,
     *     discount_cents: int,
     *     base_cents: int,
     *     tax_cents: int,
     *     total_cents: int,
     *     settings_version: int,
     *     fingerprint: string
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
