<?php

namespace Tests\Unit;

use App\Data\CartLineData;
use App\Enums\PriceTier;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('examples')]
    public function test_exact_selected_tier(PriceTier $tier, int $quantity, int $unit, int $base, int $tax, int $total): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, $quantity)], $tier);
        $this->assertSame($unit, $quote->lines[0]->unitBaseCents);
        $this->assertSame($base, $quote->subtotalCents);
        $this->assertSame($base, $quote->baseCents);
        $this->assertSame(0, $quote->discountCents);
        $this->assertSame($tax, $quote->taxCents);
        $this->assertSame($total, $quote->totalCents);
        $this->assertSame($tier, $quote->tier);
        $this->assertSame('MXN', $quote->currency);
        $this->assertSame('automatic', $quote->lines[0]->priceSource);
        $this->assertSame(0, $quote->lines[0]->discountCents);
        $this->assertSame($base, $quote->lines[0]->discountedBaseCents);
        $this->assertSame(1, $quote->settingsVersion);
    }

    public static function examples(): array
    {
        return [[PriceTier::Public, 1, 130000, 130000, 20800, 150800], [PriceTier::Installer, 1, 110000, 110000, 17600, 127600], [PriceTier::Public, 3, 130000, 390000, 62400, 452400]];
    }

    #[DataProvider('roundingExamples')]
    public function test_rounding_and_tax_per_line(int $cost, int $markup, int $taxRate, int $quantity, int $unit, int $tax, int $total): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => $markup, 'tax_basis_points' => $taxRate]);
        $product = Product::factory()->create(['base_cost_cents' => $cost]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, $quantity)], PriceTier::Public);
        $this->assertSame($unit, $quote->lines[0]->unitBaseCents);
        $this->assertSame($tax, $quote->taxCents);
        $this->assertSame($total, $quote->totalCents);
    }

    public static function roundingExamples(): array
    {
        return [[1, 4999, 0, 3, 1, 0, 3], [1, 5000, 0, 3, 2, 0, 6], [1, 5001, 0, 3, 2, 0, 6], [3, 0, 1600, 3, 3, 1, 10], [1, 0, 4999, 1, 1, 0, 1], [1, 0, 5000, 1, 1, 1, 2], [1, 0, 5001, 1, 1, 1, 2]];
    }

    public function test_merges_duplicates_and_canonicalizes_fingerprint(): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => 0]);
        $first = Product::factory()->create(['base_cost_cents' => 3]);
        $second = Product::factory()->create(['base_cost_cents' => 100]);
        $service = app(PricingService::class);
        $quote = $service->quote([new CartLineData($second->id, 1), new CartLineData($first->id, 1), new CartLineData($first->id, 2)], PriceTier::Public);
        $canonical = $service->quote([new CartLineData($first->id, 3), new CartLineData($second->id, 1)], PriceTier::Public);
        $this->assertCount(2, $quote->lines);
        $this->assertSame(3, $quote->lines[0]->quantity);
        $this->assertSame(17, $quote->taxCents);
        $this->assertSame(126, $quote->totalCents);
        $this->assertSame($canonical->fingerprint, $quote->fingerprint);
    }

    public function test_serialization_excludes_private_data(): void
    {
        StoreSetting::factory()->create(['pickup_address' => 'PRIVATE ADDRESS']);
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $serialized = json_encode($quote, JSON_THROW_ON_ERROR);
        $data = json_decode($serialized, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(150800, $data['total_cents']);
        $this->assertSame('public', $data['tier']);
        $this->assertSame(130000, $data['lines'][0]['unit_base_cents']);
        foreach (['base_cost', 'installer', 'markup', 'PRIVATE ADDRESS', '127600'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $serialized);
        }
        $this->assertSame($data, $quote->toArray());
    }

    public function test_fingerprint_tracks_commercial_changes(): void
    {
        $settings = StoreSetting::factory()->create();
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $service = app(PricingService::class);
        $original = $service->quote([new CartLineData($product->id, 1)], PriceTier::Public)->fingerprint;
        $this->assertNotSame($original, $service->quote([new CartLineData($product->id, 2)], PriceTier::Public)->fingerprint);
        $this->assertNotSame($original, $service->quote([new CartLineData($product->id, 1)], PriceTier::Installer)->fingerprint);
        foreach (['public_markup_basis_points' => [3100, 3000], 'tax_basis_points' => [1700, 1600], 'currency' => ['USD', 'MXN'], 'version' => [2, 1]] as $key => [$value, $restore]) {
            $settings->update([$key => $value]);
            $this->assertNotSame($original, $service->quote([new CartLineData($product->id, 1)], PriceTier::Public)->fingerprint);
            $settings->update([$key => $restore]);
        }
        $product->update(['base_cost_cents' => 100001]);
        $this->assertNotSame($original, $service->quote([new CartLineData($product->id, 1)], PriceTier::Public)->fingerprint);
    }

    #[DataProvider('invalidLines')]
    public function test_invalid_cart_line(int $id, int $quantity): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CartLineData($id, $quantity);
    }

    public static function invalidLines(): array
    {
        return [[0, 1], [-1, 1], [1, 0], [1, -1]];
    }

    public function test_missing_product_is_not_silently_ignored(): void
    {
        StoreSetting::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        app(PricingService::class)->quote([new CartLineData(999, 1)], PriceTier::Public);
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_pricing(int $cost, int $markup, int $tax): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => $markup, 'tax_basis_points' => $tax]);
        $product = Product::factory()->create(['base_cost_cents' => $cost]);
        $this->expectException(InvalidArgumentException::class);
        app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
    }

    public static function invalidValues(): array
    {
        return [[0, 0, 0], [-1, 0, 0], [1, -1, 0], [1, 0, -1]];
    }

    public function test_large_representable_price_avoids_intermediate_overflow(): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => 0, 'tax_basis_points' => 0]);
        $product = Product::factory()->create(['base_cost_cents' => PHP_INT_MAX]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $this->assertSame(PHP_INT_MAX, $quote->totalCents);
    }

    #[DataProvider('overflowCases')]
    public function test_overflow_is_rejected(int $cost, int $quantity, int $markup, int $tax, bool $duplicate): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => $markup, 'tax_basis_points' => $tax]);
        $product = Product::factory()->create(['base_cost_cents' => $cost]);
        $lines = [new CartLineData($product->id, $quantity)];
        if ($duplicate) {
            $lines[] = new CartLineData($product->id, 1);
        }
        $this->expectException(OverflowException::class);
        app(PricingService::class)->quote($lines, PriceTier::Public);
    }

    public static function overflowCases(): array
    {
        return [[PHP_INT_MAX, 1, 1, 0, false], [PHP_INT_MAX, 2, 0, 0, false], [PHP_INT_MAX, 1, 0, 1, false], [1, PHP_INT_MAX, 0, 0, true]];
    }

    public function test_empty_cart_is_zero(): void
    {
        StoreSetting::factory()->create();
        $quote = app(PricingService::class)->quote([], PriceTier::Public);
        $this->assertSame([], $quote->lines);
        $this->assertSame(0, $quote->totalCents);
    }

    public function test_sum_of_valid_lines_cannot_overflow(): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => 0, 'tax_basis_points' => 0]);
        $first = Product::factory()->create(['base_cost_cents' => intdiv(PHP_INT_MAX, 2) + 1]);
        $second = Product::factory()->create(['base_cost_cents' => intdiv(PHP_INT_MAX, 2) + 1]);
        $this->expectException(OverflowException::class);
        app(PricingService::class)->quote([new CartLineData($first->id, 1), new CartLineData($second->id, 1)], PriceTier::Public);
    }

    public function test_large_fractional_rates_remain_exact(): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => 1, 'tax_basis_points' => 1]);
        $product = Product::factory()->create(['base_cost_cents' => 9000000000000000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $this->assertSame(9000900000000000, $quote->lines[0]->unitBaseCents);
        $this->assertSame(900090000000, $quote->taxCents);
        $this->assertSame(9001800090000000, $quote->totalCents);
    }

    #[DataProvider('malformedCarts')]
    public function test_rejects_malformed_cart_arrays(array $lines): void
    {
        StoreSetting::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        app(PricingService::class)->quote($lines, PriceTier::Public);
    }

    public static function malformedCarts(): array
    {
        return [[['product_id' => 1, 'quantity' => 1]], [[null]], [[new \stdClass]]];
    }

    public function test_previously_returned_quote_does_not_change_with_database_updates(): void
    {
        StoreSetting::factory()->create();
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $service = app(PricingService::class);
        $quote = $service->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $old = $quote->toArray();
        $product->update(['base_cost_cents' => 200000]);
        $this->assertSame(301600, $service->quote([new CartLineData($product->id, 1)], PriceTier::Public)->totalCents);
        $this->assertSame($old, $quote->toArray());
        $this->assertSame(150800, $quote->totalCents);
    }

    #[DataProvider('invalidSettings')]
    public function test_rejects_invalid_quote_configuration(string $field, int|string $value): void
    {
        StoreSetting::factory()->create([$field => $value]);
        $product = Product::factory()->create();
        $this->expectException(InvalidArgumentException::class);
        app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
    }

    public static function invalidSettings(): array
    {
        return [['currency', ''], ['currency', 'mxn'], ['version', 0]];
    }

    public function test_maximum_markup_can_have_a_representable_result(): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => PHP_INT_MAX, 'tax_basis_points' => 0]);
        $product = Product::factory()->create(['base_cost_cents' => 1]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $this->assertSame(922337203685479, $quote->totalCents);
    }

    public function test_tax_is_rounded_separately_for_distinct_products(): void
    {
        StoreSetting::factory()->create(['public_markup_basis_points' => 0]);
        $first = Product::factory()->create(['base_cost_cents' => 3]);
        $second = Product::factory()->create(['base_cost_cents' => 3]);
        $service = app(PricingService::class);
        $quote = $service->quote([new CartLineData($first->id, 1), new CartLineData($second->id, 1)], PriceTier::Public);
        $this->assertSame(0, $quote->taxCents);
        $this->assertSame(6, $quote->totalCents);
        $this->assertNotSame($service->quote([new CartLineData($first->id, 1)], PriceTier::Public)->fingerprint, $service->quote([new CartLineData($second->id, 1)], PriceTier::Public)->fingerprint);
    }
}
