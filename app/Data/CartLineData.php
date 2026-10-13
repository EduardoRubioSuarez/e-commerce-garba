<?php

declare(strict_types=1);

namespace App\Data;

use InvalidArgumentException;
use JsonSerializable;

final readonly class CartLineData implements JsonSerializable
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {
        if ($this->productId <= 0 || $this->quantity <= 0) {
            throw new InvalidArgumentException('Product ID and quantity must be greater than zero.');
        }
    }

    /**
     * @return array{product_id: int, quantity: int}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
        ];
    }

    /**
     * @return array{product_id: int, quantity: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
