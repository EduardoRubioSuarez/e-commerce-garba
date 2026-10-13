<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'name',
    'slug',
    'description',
    'specifications',
    'equipment_contents',
    'capacity_btu',
    'base_cost_cents',
    'is_published',
    'is_active',
])]
#[Hidden([
    'base_cost_cents',
    'priceOverrides',
    'price_overrides',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_cost_cents' => 'integer',
            'capacity_btu' => 'integer',
            'is_published' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the category that owns the product.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<PriceOverride, $this> */
    public function priceOverrides(): HasMany
    {
        return $this->hasMany(PriceOverride::class);
    }
}
