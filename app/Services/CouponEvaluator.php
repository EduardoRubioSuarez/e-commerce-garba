<?php

namespace App\Services;

use App\Data\QuoteData;
use App\Enums\CouponKind;
use App\Enums\CouponScope;
use App\Enums\PriceTier;
use App\Models\Coupon;
use InvalidArgumentException;
use OverflowException;

class CouponEvaluator
{
    /** @return list<int> */
    public function eligibleLines(QuoteData $quote, Coupon $coupon): array
    {
        if ($coupon->value < 1 || $coupon->minimum_base_minor < 0 || $coupon->max_uses < 1
            || ($coupon->kind === CouponKind::Percentage && $coupon->value > 9000)
            || ($coupon->starts_at !== null && $coupon->ends_at !== null && $coupon->starts_at->gte($coupon->ends_at))) {
            throw new InvalidArgumentException('Invalid coupon definition.');
        }
        $now = now();
        if (! $coupon->is_active || $coupon->currency !== $quote->currency
            || ($coupon->starts_at !== null && $now->lt($coupon->starts_at))
            || ($coupon->ends_at !== null && $now->gte($coupon->ends_at))
            || ($quote->tier === PriceTier::Installer && ! $coupon->applies_to_installers)) {
            return [];
        }
        $selected = $coupon->scope === CouponScope::Selected ? $coupon->products->modelKeys() : [];
        $eligible = [];
        $base = 0;
        foreach ($quote->lines as $index => $line) {
            if (($line->priceSource === 'manual' && ! $coupon->applies_to_manual_prices)
                || ($coupon->scope === CouponScope::Selected && ! in_array($line->productId, $selected, true))) {
                continue;
            }
            if ($line->baseCents < 0 || $base > PHP_INT_MAX - $line->baseCents) {
                throw new OverflowException('Eligible base is not representable.');
            }
            $base += $line->baseCents;
            $eligible[] = $index;
        }

        return $base > 0 && $base >= $coupon->minimum_base_minor ? $eligible : [];
    }

    /** @return array<string, mixed> */
    public function snapshot(Coupon $coupon): array
    {
        return ['id' => $coupon->id, 'code' => $coupon->code, 'kind' => $coupon->kind->value,
            'value' => $coupon->value, 'currency' => $coupon->currency, 'starts_at' => $coupon->starts_at?->utc()->toIso8601String(),
            'ends_at' => $coupon->ends_at?->utc()->toIso8601String(), 'minimum_base_minor' => $coupon->minimum_base_minor,
            'max_uses' => $coupon->max_uses, 'applies_to_installers' => $coupon->applies_to_installers,
            'applies_to_manual_prices' => $coupon->applies_to_manual_prices, 'scope' => $coupon->scope->value,
            'is_active' => $coupon->is_active, 'product_ids' => $coupon->scope === CouponScope::Selected
                ? $coupon->products->sortBy('id')->modelKeys() : []];
    }
}
