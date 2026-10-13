<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceTier;
use Database\Factories\PriceOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property PriceTier $tier
 * @property int $base_price_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
#[Fillable(['product_id', 'tier', 'base_price_cents'])]
class PriceOverride extends Model
{
    /** @use HasFactory<PriceOverrideFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'product_id' => 'integer',
            'base_price_cents' => 'integer',
            'tier' => PriceTier::class,
        ];
    }

    /**
     * Get the product that owns the price override.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
