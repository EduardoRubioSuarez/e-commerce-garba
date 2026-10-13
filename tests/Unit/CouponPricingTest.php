<?php

namespace Tests\Unit;

use App\Data\CartLineData;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\PriceOverride;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CouponEvaluator;
use App\Services\DiscountAllocator;
use App\Services\PricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CouponPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        view()->share('errors', new ViewErrorBag);
        StoreSetting::factory()->create();
    }

    public function test_eligible_minimum_counts_only_selected_automatic_lines(): void
    {
        $first = Product::factory()->create(['base_cost_cents' => 100000]);
        $other = Product::factory()->create(['base_cost_cents' => 200000]);
        $quote = app(PricingService::class)->quote([new CartLineData($other->id, 1), new CartLineData($first->id, 1)], PriceTier::Public);
        $coupon = Coupon::factory()->create(['scope' => 'selected', 'minimum_base_minor' => 130000]);
        $coupon->products()->attach($first);
        $this->assertSame([0], app(CouponEvaluator::class)->eligibleLines($quote, $coupon));
        $coupon->minimum_base_minor = 130001;
        $this->assertSame([], app(CouponEvaluator::class)->eligibleLines($quote, $coupon));
    }

    public function test_installer_and_manual_prices_require_independent_flags(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        PriceOverride::factory()->create(['product_id' => $product->id, 'tier' => PriceTier::Installer, 'base_price_cents' => 110000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Installer);
        $coupon = Coupon::factory()->create();
        $evaluator = app(CouponEvaluator::class);
        $this->assertSame([], $evaluator->eligibleLines($quote, $coupon));
        $coupon->applies_to_installers = true;
        $this->assertSame([], $evaluator->eligibleLines($quote, $coupon));
        $coupon->applies_to_manual_prices = true;
        $this->assertSame([0], $evaluator->eligibleLines($quote, $coupon));
    }

    public function test_validity_includes_start_and_excludes_end_in_utc(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $coupon = Coupon::factory()->create(['starts_at' => '2026-10-07 16:00:00', 'ends_at' => '2026-10-07 17:00:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-07T15:59:59Z'));
        $this->assertSame([], app(CouponEvaluator::class)->eligibleLines($quote, $coupon));
        $this->travelTo(CarbonImmutable::parse('2026-10-07T16:00:00Z'));
        $this->assertSame([0], app(CouponEvaluator::class)->eligibleLines($quote, $coupon));
        $this->travelTo(CarbonImmutable::parse('2026-10-07T17:00:00Z'));
        $this->assertSame([], app(CouponEvaluator::class)->eligibleLines($quote, $coupon));
    }

    public function test_disabled_wrong_currency_and_empty_selected_never_discount(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        foreach ([['is_active' => false], ['currency' => 'USD'], ['scope' => 'selected']] as $attributes) {
            $this->assertSame([], app(CouponEvaluator::class)->eligibleLines($quote, Coupon::factory()->create($attributes)));
        }
    }

    public function test_invalid_percentage_cannot_bypass_the_ninety_percent_limit(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public);
        $this->expectException(\InvalidArgumentException::class);
        app(CouponEvaluator::class)->eligibleLines($quote, Coupon::factory()->create(['value' => 9001]));
    }

    /** @return array<string, array{list<int>, int, list<int>}> */
    public static function allocations(): array
    {
        return ['proportional' => [[101, 202], 30, [10, 20]], 'stable tie' => [[1, 1, 1], 1, [1, 0, 0]],
            'zero bases' => [[0, 1, 1], 1, [0, 1, 0]], 'all base' => [[101, 202], 303, [101, 202]],
            'empty' => [[], 0, []], 'no discount' => [[10, 20], 0, [0, 0]],
            'intermediate multiplication overflow' => [[PHP_INT_MAX - 1, 1], PHP_INT_MAX - 1, [PHP_INT_MAX - 2, 1]]];
    }

    #[DataProvider('allocations')]
    public function test_exact_largest_remainder_allocation(array $bases, int $discount, array $expected): void
    {
        $this->assertSame($expected, app(DiscountAllocator::class)->allocate($bases, $discount));
    }

    /** @return array<string, array{array, int}> */
    public static function invalidAllocations(): array
    {
        return ['too much' => [[1, 2], 4], 'negative discount' => [[1, 2], -1], 'negative base' => [[-1, 2], 0],
            'float base' => [[1.0, 2], 1], 'non list' => [[2 => 5], 1], 'no base' => [[], 1],
            'sum overflow' => [[PHP_INT_MAX, 1], 1]];
    }

    #[DataProvider('invalidAllocations')]
    public function test_allocation_rejects_invalid_or_unrepresentable_input(array $bases, int $discount): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(DiscountAllocator::class)->allocate($bases, $discount);
    }

    public function test_allocation_conserves_discount_and_bounds_every_line_across_many_sizes(): void
    {
        for ($size = 1; $size <= 50; $size++) {
            $bases = [$size * 101, $size * 37 + 1, $size % 7 + 1];
            $discount = $size * 13;
            $result = app(DiscountAllocator::class)->allocate($bases, $discount);
            $this->assertSame($discount, array_sum($result));
            foreach ($result as $index => $amount) {
                $this->assertGreaterThanOrEqual(0, $amount);
                $this->assertLessThanOrEqual($bases[$index], $amount);
            }
        }
    }

    public function test_promo10_recomputes_tax_after_discount_and_freezes_rule_snapshot(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['code' => 'PROMO10']);
        $lines = [new CartLineData($product->id, 1)];
        $pricing = app(PricingService::class);
        $original = $pricing->quote($lines, PriceTier::Public);
        $quote = $pricing->quote($lines, PriceTier::Public, $coupon);
        $this->assertSame(130000, $quote->subtotalCents);
        $this->assertSame(13000, $quote->discountCents);
        $this->assertSame(117000, $quote->baseCents);
        $this->assertSame(18720, $quote->taxCents);
        $this->assertSame(135720, $quote->totalCents);
        $this->assertSame(13000, $quote->lines[0]->discountCents);
        $this->assertSame(1000, $quote->couponSnapshot['value']);
        $before = $quote->toArray();
        $coupon->update(['value' => 2000]);
        $this->assertSame($before, $quote->toArray());
        $this->assertNotSame($quote->fingerprint, $pricing->quote($lines, PriceTier::Public, $coupon)->fingerprint);
        $this->assertSame($original->toArray(), $pricing->quote($lines, PriceTier::Public)->toArray());
        $this->assertArrayNotHasKey('coupon', $original->toArray());
        $this->assertStringNotContainsString('base_cost', json_encode($quote));
    }

    public function test_fixed_coupon_is_capped_and_reports_requested_and_applied_amounts(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public,
            Coupon::factory()->create(['kind' => 'fixed', 'value' => 200000]));
        $this->assertSame(117000, $quote->discountCents);
        $this->assertSame(200000, $quote->couponSnapshot['requested_discount_minor']);
        $this->assertSame(117000, $quote->couponSnapshot['applied_discount_minor']);
        $this->assertTrue($quote->couponSnapshot['is_capped']);
        $this->assertSame(15080, $quote->totalCents);
    }

    public function test_selected_scope_changes_discount_and_fingerprint_without_changing_other_lines(): void
    {
        $first = Product::factory()->create(['base_cost_cents' => 100000]);
        $second = Product::factory()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['scope' => 'selected']);
        $coupon->products()->attach($first);
        $lines = [new CartLineData($second->id, 1), new CartLineData($first->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $this->assertSame(13000, $quote->lines[0]->discountCents);
        $this->assertSame(0, $quote->lines[1]->discountCents);
        $this->assertSame(150800, $quote->lines[1]->totalCents);
        $this->assertSame([$first->id], $quote->couponSnapshot['product_ids']);
        $coupon->update(['max_uses' => 51]);
        $other = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $this->assertSame($quote->totalCents, $other->totalCents);
        $this->assertNotSame($quote->fingerprint, $other->fingerprint);
    }

    public function test_no_eligible_base_keeps_the_total_and_final_stripe_minimum_is_checked(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, Coupon::factory()->create(['minimum_base_minor' => 130001]));
        $this->assertSame(0, $quote->discountCents);
        $this->assertSame(150800, $quote->totalCents);
        config(['store.checkout.minimum_charge_cents' => 140000]);
        $this->expectException(\InvalidArgumentException::class);
        app(PricingService::class)->quote($lines, PriceTier::Public, Coupon::factory()->create());
    }

    public function test_capped_discount_is_visible_in_cart_and_review_before_explicit_consent(): void
    {
        $product = Product::factory()->create(['base_cost_cents' => 100000]);
        $quote = app(PricingService::class)->quote([new CartLineData($product->id, 1)], PriceTier::Public,
            Coupon::factory()->create(['kind' => 'fixed', 'value' => 200000]));
        $data = $quote->toArray();
        $data['lines'][0]['slug'] = $product->slug;
        $this->view('store.cart', ['data' => $data, 'tier' => PriceTier::Public])
            ->assertSee('Descuento sin IVA')->assertSee('$1,170.00')->assertSee('$2,000.00')->assertSee('recortado');
        $this->view('store.checkout-review', ['step' => 'review', 'data' => ['changed' => true,
            'quote' => $quote->toArray(), 'contact' => ['name' => 'Prueba', 'email' => 'cliente@example.test', 'phone' => '+52 614 123 4567'],
            'review_key' => '1a0a2107-df72-4c5e-9b1f-9bc08539fbb5', 'fingerprint' => $quote->fingerprint]])
            ->assertSee('recortado')->assertSee('$1,170.00')->assertSee('$2,000.00')->assertSee('$150.80')
            ->assertSee('accept_terms')->assertSee('Revisa el total actualizado');
    }

    public function test_product_order_and_duplicate_cart_lines_do_not_change_discount_distribution(): void
    {
        StoreSetting::current()->update(['public_markup_basis_points' => 0, 'tax_basis_points' => 0]);
        config(['store.checkout.minimum_charge_cents' => 0]);
        $products = Product::factory()->count(3)->create(['base_cost_cents' => 1]);
        $coupon = Coupon::factory()->create(['kind' => 'fixed', 'value' => 1]);
        $pricing = app(PricingService::class);
        $normal = $products->map(fn ($product) => new CartLineData($product->id, 1))->all();
        $quote = $pricing->quote($normal, PriceTier::Public, $coupon);
        $this->assertSame([1, 0, 0], array_map(fn ($line) => $line->discountCents, $quote->lines));
        $this->assertSame($quote->toArray(), $pricing->quote(array_reverse($normal), PriceTier::Public, $coupon)->toArray());
        $doubled = $products->map(fn ($product) => new CartLineData($product->id, 2))->all();
        $this->assertSame($pricing->quote($doubled, PriceTier::Public, $coupon)->toArray(),
            $pricing->quote(array_merge($normal, array_reverse($normal)), PriceTier::Public, $coupon)->toArray());
    }
}
