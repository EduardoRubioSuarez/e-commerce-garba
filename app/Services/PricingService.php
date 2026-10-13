<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CartLineData;
use App\Data\QuoteData;
use App\Data\QuoteLineData;
use App\Enums\CouponKind;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use InvalidArgumentException;
use OverflowException;

class PricingService
{
    /**
     * Calculate a quote for the given cart lines and price tier.
     *
     * @param  list<CartLineData>  $lines
     *
     * @throws InvalidArgumentException
     * @throws OverflowException
     */
    public function quote(array $lines, PriceTier $tier, ?Coupon $coupon = null): QuoteData
    {
        if (! array_is_list($lines)) {
            throw new InvalidArgumentException('Cart lines must be a sequential list.');
        }

        $setting = StoreSetting::current();
        $this->validateSettings($setting);
        $currency = trim((string) $setting->currency);
        $version = (int) $setting->version;

        if ($lines === []) {
            $fingerprint = QuoteData::computeFingerprint(
                lines: [],
                tier: $tier,
                currency: $currency,
                subtotalCents: 0,
                discountCents: 0,
                baseCents: 0,
                taxCents: 0,
                totalCents: 0,
                settingsVersion: $version,
            );

            $empty = new QuoteData(
                lines: [],
                tier: $tier,
                currency: $currency,
                subtotalCents: 0,
                discountCents: 0,
                baseCents: 0,
                taxCents: 0,
                totalCents: 0,
                settingsVersion: $version,
                fingerprint: $fingerprint,
            );

            return $coupon === null ? $empty : $this->applyCoupon($empty, $coupon);
        }

        /** @var array<int, int> $mergedQuantities */
        $mergedQuantities = [];

        foreach ($lines as $line) {
            if (! $line instanceof CartLineData) {
                throw new InvalidArgumentException('Each line must be an instance of CartLineData.');
            }

            $productId = $line->productId;
            $qty = $line->quantity;

            if (isset($mergedQuantities[$productId])) {
                if ($mergedQuantities[$productId] > PHP_INT_MAX - $qty) {
                    throw new OverflowException('Quantity overflow when combining cart lines.');
                }
                $mergedQuantities[$productId] += $qty;
            } else {
                $mergedQuantities[$productId] = $qty;
            }
        }

        ksort($mergedQuantities, SORT_NUMERIC);

        $productIds = array_keys($mergedQuantities);
        $products = Product::query()->with('priceOverrides')->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($productIds as $productId) {
            if (! $products->has($productId)) {
                throw new InvalidArgumentException("Product with ID {$productId} was not found.");
            }

            /** @var Product $product */
            $product = $products->get($productId);
            $cost = (int) $product->base_cost_cents;

            if ($cost <= 0) {
                throw new InvalidArgumentException("Product cost must be greater than zero for product ID {$productId}.");
            }
        }

        /** @var list<QuoteLineData> $quoteLines */
        $quoteLines = [];
        $subtotalCents = 0;
        $taxCentsSum = 0;
        $totalCentsSum = 0;

        foreach ($mergedQuantities as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get($productId);
            $quoteLine = $this->quoteLine($product, $quantity, $tier, $setting);
            $baseCents = $quoteLine->baseCents;
            $taxCents = $quoteLine->taxCents;
            $totalCents = $quoteLine->totalCents;

            if ($subtotalCents > PHP_INT_MAX - $baseCents) {
                throw new OverflowException('Integer overflow in subtotal calculation.');
            }
            $subtotalCents += $baseCents;

            if ($taxCentsSum > PHP_INT_MAX - $taxCents) {
                throw new OverflowException('Integer overflow in tax calculation.');
            }
            $taxCentsSum += $taxCents;

            if ($totalCentsSum > PHP_INT_MAX - $totalCents) {
                throw new OverflowException('Integer overflow in overall total calculation.');
            }
            $totalCentsSum += $totalCents;

            $quoteLines[] = $quoteLine;
        }

        $fingerprint = QuoteData::computeFingerprint(
            lines: $quoteLines,
            tier: $tier,
            currency: $currency,
            subtotalCents: $subtotalCents,
            discountCents: 0,
            baseCents: $subtotalCents,
            taxCents: $taxCentsSum,
            totalCents: $totalCentsSum,
            settingsVersion: $version,
        );

        $quote = new QuoteData(
            lines: $quoteLines,
            tier: $tier,
            currency: $currency,
            subtotalCents: $subtotalCents,
            discountCents: 0,
            baseCents: $subtotalCents,
            taxCents: $taxCentsSum,
            totalCents: $totalCentsSum,
            settingsVersion: $version,
            fingerprint: $fingerprint,
        );

        return $coupon === null ? $quote : $this->applyCoupon($quote, $coupon);
    }

    public function applyCoupon(QuoteData $quote, Coupon $coupon): QuoteData
    {
        $evaluator = app(CouponEvaluator::class);
        $eligible = $evaluator->eligibleLines($quote, $coupon);
        $bases = array_map(fn (int $index): int => $quote->lines[$index]->baseCents, $eligible);
        $base = 0;
        foreach ($bases as $amount) {
            $base += $amount;
        }
        $requested = $coupon->kind === CouponKind::Fixed ? $coupon->value : self::multiplyDivideRoundHalfUp($base, $coupon->value);
        $cap = intdiv($base, 10000) * 9000 + intdiv(($base % 10000) * 9000, 10000);
        $discount = $eligible === [] ? 0 : min($requested, $cap);
        $allocation = app(DiscountAllocator::class)->allocate($bases, $discount);
        $discounts = array_fill(0, count($quote->lines), 0);
        foreach ($eligible as $position => $index) {
            $discounts[$index] = $allocation[$position];
        }
        $lines = [];
        $tax = 0;
        $total = 0;
        foreach ($quote->lines as $index => $line) {
            $lineBase = $line->baseCents - $discounts[$index];
            $lineTax = self::multiplyDivideRoundHalfUp($lineBase, $line->taxBasisPoints);
            if ($lineBase > PHP_INT_MAX - $lineTax || $tax > PHP_INT_MAX - $lineTax || $total > PHP_INT_MAX - ($lineBase + $lineTax)) {
                throw new OverflowException('Discounted quote is not representable.');
            }
            $tax += $lineTax;
            $total += $lineBase + $lineTax;
            $lines[] = new QuoteLineData($line->productId, $line->name, $line->quantity, $line->unitBaseCents,
                $line->priceSource, $line->baseCents, $discounts[$index], $lineBase, $line->taxBasisPoints, $lineTax, $lineBase + $lineTax);
        }
        if ($discount > 0 && $total < (int) config('store.checkout.minimum_charge_cents', 1000)) {
            throw new InvalidArgumentException('El total con descuento no alcanza el mínimo de pago.');
        }
        $snapshot = $evaluator->snapshot($coupon);
        $snapshot['eligible_base_minor'] = $base;
        $snapshot['requested_discount_minor'] = $requested;
        $snapshot['applied_discount_minor'] = $discount;
        $snapshot['is_capped'] = $eligible !== [] && $requested > $discount;

        return new QuoteData($lines, $quote->tier, $quote->currency, $quote->subtotalCents, $discount,
            $quote->subtotalCents - $discount, $tax, $total, $quote->settingsVersion, couponSnapshot: $snapshot);
    }

    /**
     * Quote one persisted product using the same calculation as a cart.
     */
    public function quoteLine(Product $product, int $quantity, PriceTier $tier, StoreSetting $setting): QuoteLineData
    {
        $this->validateSettings($setting);
        $cost = (int) $product->base_cost_cents;
        $markup = match ($tier) {
            PriceTier::Public => $setting->public_markup_basis_points,
            PriceTier::Installer => $setting->installer_markup_basis_points,
        };
        $taxRate = (int) $setting->tax_basis_points;
        if ($quantity < 1 || $cost < 1 || $markup < 0 || $taxRate < 0) {
            throw new InvalidArgumentException('Quantity, cost and rates must be valid.');
        }

        /** @var PriceOverride|null $override */
        $override = $product->priceOverrides->first(fn (PriceOverride $price): bool => $price->tier === $tier);
        if ($override !== null) {
            $unit = (int) $override->base_price_cents;
            if ($unit < 1) {
                throw new InvalidArgumentException('The effective unit price must be positive.');
            }
        } else {
            $markupCents = self::multiplyDivideRoundHalfUp($cost, $markup);
            if ($cost > PHP_INT_MAX - $markupCents) {
                throw new OverflowException('Integer overflow in unit price calculation.');
            }
            $unit = $cost + $markupCents;
        }
        if ($quantity > intdiv(PHP_INT_MAX, $unit)) {
            throw new OverflowException('Integer overflow in line base calculation.');
        }
        $base = $unit * $quantity;
        $tax = self::multiplyDivideRoundHalfUp($base, $taxRate);
        if ($base > PHP_INT_MAX - $tax) {
            throw new OverflowException('Integer overflow in line total calculation.');
        }

        return new QuoteLineData(
            productId: $product->id,
            name: (string) $product->name,
            quantity: $quantity,
            unitBaseCents: $unit,
            priceSource: $override === null ? 'automatic' : 'manual',
            baseCents: $base,
            discountCents: 0,
            discountedBaseCents: $base,
            taxBasisPoints: $taxRate,
            taxCents: $tax,
            totalCents: $base + $tax,
        );
    }

    private function validateSettings(StoreSetting $setting): void
    {
        if ($setting->public_markup_basis_points < 0 || $setting->installer_markup_basis_points < 0 || $setting->tax_basis_points < 0) {
            throw new InvalidArgumentException('Markup and tax basis points must not be negative.');
        }
        if (preg_match('/^[A-Z]{3}$/D', trim((string) $setting->currency)) !== 1) {
            throw new InvalidArgumentException('Store currency is invalid.');
        }
        if ($setting->version <= 0) {
            throw new InvalidArgumentException('Store settings version is invalid.');
        }
    }

    /**
     * Exact multiplication and division by 10000 with round-half-up.
     *
     * x = q * 10000 + r
     * b = s * 10000 + t
     * floor(x * b / 10000) = q * b + r * s + floor(r * t / 10000)
     * remainder = (r * t) % 10000
     * add 1 if remainder >= 5000
     *
     *
     * @throws OverflowException
     */
    private static function multiplyDivideRoundHalfUp(int $x, int $b): int
    {
        if ($x === 0 || $b === 0) {
            return 0;
        }

        $q = intdiv($x, 10000);
        $r = $x % 10000;

        $s = intdiv($b, 10000);
        $t = $b % 10000;

        $qb = 0;
        if ($q > 0) {
            if ($b > intdiv(PHP_INT_MAX, $q)) {
                throw new OverflowException('Integer overflow in q * b multiplication.');
            }
            $qb = $q * $b;
        }

        $rs = 0;
        if ($r > 0 && $s > 0) {
            if ($s > intdiv(PHP_INT_MAX, $r)) {
                throw new OverflowException('Integer overflow in r * s multiplication.');
            }
            $rs = $r * $s;
        }

        $rt = $r * $t;
        $rtDiv = intdiv($rt, 10000);
        $remainder = $rt % 10000;
        $roundUp = ($remainder >= 5000) ? 1 : 0;

        if ($qb > PHP_INT_MAX - $rs) {
            throw new OverflowException('Integer overflow in sum (qb + rs).');
        }
        $sum = $qb + $rs;

        if ($sum > PHP_INT_MAX - $rtDiv) {
            throw new OverflowException('Integer overflow in sum (+ rtDiv).');
        }
        $sum += $rtDiv;

        if ($sum > PHP_INT_MAX - $roundUp) {
            throw new OverflowException('Integer overflow in sum (+ roundUp).');
        }
        $sum += $roundUp;

        return $sum;
    }
}
