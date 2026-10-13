<?php

namespace App\Models;

use App\Enums\CouponKind;
use App\Enums\CouponScope;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

#[Fillable(['code', 'kind', 'value', 'currency', 'starts_at', 'ends_at', 'minimum_base_minor', 'max_uses', 'applies_to_installers', 'applies_to_manual_prices', 'scope', 'is_active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $coupon): void {
            $coupon->code = strtoupper(trim($coupon->code));
        });
        static::deleting(function (): never {
            throw new LogicException('Coupon history must be preserved; deactivate instead.');
        });
    }

    protected function casts(): array
    {
        return ['kind' => CouponKind::class, 'scope' => CouponScope::class, 'value' => 'integer',
            'minimum_base_minor' => 'integer', 'max_uses' => 'integer', 'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime', 'applies_to_installers' => 'boolean',
            'applies_to_manual_prices' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
