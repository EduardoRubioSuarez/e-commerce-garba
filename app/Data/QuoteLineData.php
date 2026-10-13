<?php

declare(strict_types=1);

namespace App\Data;

use JsonSerializable;

final readonly class QuoteLineData implements JsonSerializable
{
    public int $discountedBaseCents;

    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public int $unitBaseCents,
        public string $priceSource = 'automatic',
        public int $baseCents = 0,
        public int $discountCents = 0,
        ?int $discountedBaseCents = null,
        public int $taxBasisPoints = 0,
        public int $taxCents = 0,
        public int $totalCents = 0,
    ) {
        $this->discountedBaseCents = $discountedBaseCents ?? $this->baseCents;
    }

    /**
     * @return array{
     *     product_id: int,
     *     name: string,
     *     quantity: int,
     *     unit_base_cents: int,
     *     price_source: string,
     *     base_cents: int,
     *     discount_cents: int,
     *     discounted_base_cents: int,
     *     tax_basis_points: int,
     *     tax_cents: int,
     *     total_cents: int
     * }
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_base_cents' => $this->unitBaseCents,
            'price_source' => $this->priceSource,
            'base_cents' => $this->baseCents,
            'discount_cents' => $this->discountCents,
            'discounted_base_cents' => $this->discountedBaseCents,
            'tax_basis_points' => $this->taxBasisPoints,
            'tax_cents' => $this->taxCents,
            'total_cents' => $this->totalCents,
        ];
    }

    /**
     * @return array{
     *     product_id: int,
     *     name: string,
     *     quantity: int,
     *     unit_base_cents: int,
     *     price_source: string,
     *     base_cents: int,
     *     discount_cents: int,
     *     discounted_base_cents: int,
     *     tax_basis_points: int,
     *     tax_cents: int,
     *     total_cents: int
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
