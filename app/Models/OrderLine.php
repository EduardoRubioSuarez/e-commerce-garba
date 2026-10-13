<?php

namespace App\Models;

use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Guarded(['*'])]
#[Hidden(['id', 'order_id'])]
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $line): void {
            if ($line->isDirty()) {
                throw new LogicException('An accepted order line cannot be changed.');
            }
        });
        static::deleting(function (): never {
            throw new LogicException('An accepted order line must be preserved.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer', 'product_id' => 'integer', 'quantity' => 'integer',
            'unit_base_cents' => 'integer', 'base_cents' => 'integer', 'discount_cents' => 'integer',
            'discounted_base_cents' => 'integer', 'tax_basis_points' => 'integer',
            'tax_cents' => 'integer', 'total_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
