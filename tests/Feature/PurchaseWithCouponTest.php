<?php

namespace Tests\Feature;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CheckoutService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseWithCouponTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.stripe.enabled' => false]);
        StoreSetting::factory()->create();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_confirmation_atomically_stores_promo10_snapshot_attempt_and_reservation(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $contact = new ContactData('Prueba', 'fixture@example.test', '6141234567', '+526141234567');
        $attempt = app(CheckoutService::class)->start($lines, $contact, null, $quote->fingerprint, (string) Str::uuid(), ' promo10 ');
        $this->assertSame(135720, $attempt->amount_cents);
        $order = $attempt->order->fresh();
        $this->assertSame(13000, $order->discount_cents);
        $this->assertSame(18720, $order->tax_cents);
        $this->assertSame(13000, $order->coupon_snapshot['applied_discount_minor']);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertNothingSent();
    }

    public function test_validation_previews_discount_but_does_not_reserve_or_create_order(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->postJson('/checkout/cupon', ['coupon_code' => ' promo10 ', 'phone' => '6141234567', 'country' => 'MX'])
            ->assertOk()->assertJsonPath('data.total_cents', 135720);
        $this->assertSame('PROMO10', session('cart.coupon_code'));
        $this->assertDatabaseCount('coupon_uses', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_review_requires_new_acceptance_after_applying_coupon(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->post('/checkout/revision', ['name' => 'Prueba', 'email' => 'fixture@example.test', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $old = session('checkout.review');
        $this->post('/checkout/cupon', ['coupon_code' => 'PROMO10', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $this->postJson('/checkout', ['review_key' => $old['review_key'], 'accepted_fingerprint' => $old['fingerprint'], 'accept_terms' => '1'])->assertUnprocessable();
        $this->getJson('/checkout/revision')->assertOk()->assertJsonPath('data.quote.total_cents', 135720)->assertJsonPath('data.changed', true);
        $this->get('/checkout/revision')->assertOk()->assertSee('Cupón válido una vez por número de contacto');
        $review = session('checkout.review');
        $this->post('/checkout', ['review_key' => $review['review_key'], 'accepted_fingerprint' => $review['fingerprint'], 'accept_terms' => '1'])->assertRedirect();
        $this->assertSame(135720, Order::query()->sole()->total_cents);
        $this->assertSame([], session('cart.lines'));
        $this->assertNull(session('cart.coupon_code'));
    }

    public function test_coupon_changed_after_acceptance_rolls_back_the_whole_purchase(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $coupon->update(['value' => 2000]);
        try {
            app(CheckoutService::class)->start($lines, new ContactData('Prueba', 'fixture@example.test', '6141234567', '+526141234567'), null, $quote->fingerprint, (string) Str::uuid(), 'PROMO10');
            $this->fail('A changed coupon needs a new accepted quote.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('payment_attempts', 0);
            $this->assertDatabaseCount('coupon_uses', 0);
        }
    }

    public function test_eleventh_validation_is_throttled_without_creating_reservations(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        for ($index = 0; $index < 10; $index++) {
            $this->postJson('/checkout/cupon', ['coupon_code' => 'PROMO10', 'phone' => '6141234567', 'country' => 'MX'])->assertOk();
        }
        $this->postJson('/checkout/cupon', ['coupon_code' => 'PROMO10', 'phone' => '6141234567', 'country' => 'MX'])->assertStatus(429);
        $this->assertDatabaseCount('coupon_uses', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cart_displays_the_applied_coupon_before_returning_to_confirmation(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->post('/checkout/revision', ['name' => 'Prueba', 'email' => 'fixture@example.test', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $this->post('/checkout/cupon', ['coupon_code' => 'PROMO10', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $this->getJson('/carrito')->assertOk()->assertJsonPath('data.total_cents', 135720)->assertJsonPath('data.discount_cents', 13000);
        $this->get('/carrito')->assertSee('PROMO10')->assertSee('Cupón válido una vez por número de contacto');
        $this->assertDatabaseCount('coupon_uses', 0);
    }

    public function test_lost_capacity_after_preview_requires_accepting_total_without_coupon(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000, 'max_uses' => 1]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->post('/checkout/revision', ['name' => 'Prueba', 'email' => 'fixture@example.test', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $this->post('/checkout/cupon', ['coupon_code' => 'PROMO10', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $this->get('/checkout/revision')->assertOk();
        $accepted = session('checkout.review');
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        app(CheckoutService::class)->start($lines, new ContactData('Otra compra', 'private@example.test', '6147654321', '+526147654321'),
            null, $quote->fingerprint, (string) Str::uuid(), 'PROMO10');
        $this->postJson('/checkout', ['review_key' => $accepted['review_key'], 'accepted_fingerprint' => $accepted['fingerprint'], 'accept_terms' => '1'])
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code')->assertDontSee('private@example.test');
        $this->assertNull(session('cart.coupon_code'));
        $this->assertNotSame($accepted['review_key'], session('checkout.review.review_key'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->getJson('/checkout/revision')->assertJsonPath('data.quote.total_cents', 150800);
        $this->postJson('/checkout', ['review_key' => $accepted['review_key'], 'accepted_fingerprint' => $accepted['fingerprint'], 'accept_terms' => '1'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_product_unpublished_after_acceptance_cannot_create_discount_order(): void
    {
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $product->update(['is_published' => false]);
        try {
            app(CheckoutService::class)->start($lines, new ContactData('Prueba', 'fixture@example.test', '6141234567', '+526141234567'), null,
                $quote->fingerprint, (string) Str::uuid(), 'PROMO10');
            $this->fail('Current product availability must be checked under lock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('coupon_uses', 0);
        }
    }
}
