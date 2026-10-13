<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    /** @return array{name:string,email:string,phone:string,country:string} */
    private function contact(): array
    {
        return ['name' => 'Persona de prueba', 'email' => 'cliente@example.test', 'phone' => '+52 614 123 4567', 'country' => 'MX'];
    }

    private function prepareCart(): Product
    {
        $product = Product::factory()->published()->create(['name' => 'Equipo de prueba', 'base_cost_cents' => 100000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);

        return $product;
    }

    /** @return array<string,mixed> */
    private function review(): array
    {
        $this->post('/checkout/revision', $this->contact())->assertRedirect('/checkout/revision');

        return session('checkout.review');
    }

    /** @param array<string,mixed> $review
     * @return array{review_key:string,accepted_fingerprint:string,accept_terms:string}
     */
    private function confirmation(array $review): array
    {
        return ['review_key' => $review['review_key'], 'accepted_fingerprint' => $review['fingerprint'], 'accept_terms' => '1'];
    }

    public function test_paid_order_allows_new_purchase_and_preserves_history(): void
    {
        config(['services.stripe.enabled' => false]);
        Http::preventStrayRequests();
        $product = $this->prepareCart();
        $oldReview = $this->review();
        $this->post('/checkout', $this->confirmation($oldReview))->assertRedirect();
        $paidOrder = Order::query()->sole();
        $paidOrder->forceFill(['status' => OrderStatus::Paid])->save();
        $snapshot = $paidOrder->fresh()->getAttributes();
        config(['services.stripe.enabled' => true]);

        $this->get('/checkout')->assertRedirect('/carrito');
        $this->get('/pedidos/'.$paidOrder->reference)->assertOk()->assertDontSee('Iniciar nueva compra');
        $this->post('/pedidos/'.$paidOrder->reference.'/nueva-compra')->assertRedirect('/carrito')
            ->assertSessionMissing('checkout.review');
        $this->assertSame([], session('cart.lines'));
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->get('/checkout')->assertOk();
        $newReview = $this->review();
        $this->assertNotSame($oldReview['review_key'], $newReview['review_key']);
        $this->assertNotSame($oldReview['submission_key'], $newReview['submission_key']);
        $this->postJson('/checkout', $this->confirmation($oldReview))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
        config(['services.stripe.enabled' => false]);
        $this->post('/checkout', $this->confirmation($newReview))->assertRedirect();
        $this->post('/checkout', $this->confirmation($newReview))->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertSame($snapshot, $paidOrder->fresh()->getAttributes());
        $this->get('/pedidos/'.$paidOrder->reference)->assertOk();
        Http::assertNothingSent();
    }

    public function test_new_purchase_preserves_pending_order_and_cannot_access_another_order(): void
    {
        config(['services.stripe.enabled' => false]);
        $this->prepareCart();
        $review = $this->review();
        $this->post('/checkout', $this->confirmation($review))->assertRedirect();
        $pending = Order::query()->sole();
        $snapshot = $pending->getAttributes();
        $this->post('/pedidos/'.$pending->reference.'/nueva-compra')->assertRedirect('/carrito')
            ->assertSessionMissing('checkout.review');
        $this->assertSame($snapshot, $pending->fresh()->getAttributes());
        $this->get('/pedidos/'.$pending->reference)->assertOk()->assertDontSee('Iniciar nueva compra');
        $other = Order::factory()->create(['status' => OrderStatus::Paid]);
        $this->post('/pedidos/'.$other->reference.'/nueva-compra')->assertNotFound();
        $this->assertNull(session('checkout.review'));
    }

    public function test_new_review_does_not_require_resolution_of_previous_unknown_payment(): void
    {
        config(['services.stripe.enabled' => false]);
        Http::preventStrayRequests();
        $product = $this->prepareCart();
        $oldReview = $this->review();
        $this->post('/checkout', $this->confirmation($oldReview))->assertRedirect();
        $previousOrder = Order::query()->sole();
        $attempt = $previousOrder->paymentAttempts()->sole();
        $attempt->forceFill(['status' => PaymentAttemptStatus::Unknown, 'first_sent_at' => now()])->save();
        $snapshot = $previousOrder->getAttributes();
        $attemptSnapshot = $attempt->getAttributes();
        $this->assertSame([], session('cart.lines'));

        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        config(['services.stripe.enabled' => true]);
        $this->get('/checkout')->assertOk();
        $newReview = $this->review();
        $this->assertNotSame($oldReview['submission_key'], $newReview['submission_key']);
        $this->postJson('/checkout', $this->confirmation($oldReview))->assertUnprocessable();
        config(['services.stripe.enabled' => false]);
        $this->post('/checkout', $this->confirmation($newReview))->assertRedirect();
        $this->post('/checkout', $this->confirmation($newReview))->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertSame($snapshot, $previousOrder->fresh()->getAttributes());
        $this->assertSame($attemptSnapshot, $attempt->fresh()->getAttributes());
        $this->get('/pedidos/'.$previousOrder->reference)->assertOk();
        Http::assertNothingSent();
    }

    public function test_repeated_confirmation_does_not_clear_new_cart_items(): void
    {
        config(['services.stripe.enabled' => false]);
        $product = $this->prepareCart();
        $review = $this->review();
        $this->post('/checkout', $this->confirmation($review))->assertRedirect();
        $this->assertSame([], session('cart.lines'));
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 3])->assertRedirect();
        $this->post('/checkout', $this->confirmation($review))->assertRedirect();
        $this->assertSame([['product_id' => $product->id, 'quantity' => 3]], session('cart.lines'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_cart_requests_wait_for_an_in_progress_checkout_session(): void
    {
        $product = $this->prepareCart();
        config(['session.block_wait_seconds' => 0]);
        $this->withCredentials();
        $this->withCookie(config('session.cookie'), session()->getId());
        $lock = Cache::lock('session:'.session()->getId(), 60);
        $this->assertTrue($lock->get());
        $this->withoutExceptionHandling();
        $blockedMethods = [];
        try {
            foreach (['PUT', 'GET'] as $method) {
                try {
                    $this->json($method, '/carrito', ['product_id' => $product->id, 'quantity' => 3]);
                    $this->fail('A cart request must wait for the checkout session lock.');
                } catch (LockTimeoutException) {
                    $blockedMethods[] = $method;
                }
            }
            $this->assertSame(['PUT', 'GET'], $blockedMethods);
            $this->assertSame([['product_id' => $product->id, 'quantity' => 1]], session('cart.lines'));
        } finally {
            $lock->release();
            $this->withExceptionHandling();
        }
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 3])->assertRedirect();
        $this->assertSame([['product_id' => $product->id, 'quantity' => 3]], session('cart.lines'));
    }

    public function test_old_new_purchase_button_cannot_erase_a_new_review(): void
    {
        config(['services.stripe.enabled' => false]);
        $this->actingAs(User::factory()->installer()->create());
        $product = $this->prepareCart();
        $oldReview = $this->review();
        $this->post('/checkout', $this->confirmation($oldReview))->assertRedirect();
        $paid = Order::query()->sole();
        $paid->forceFill(['status' => OrderStatus::Paid])->save();
        $this->post('/pedidos/'.$paid->reference.'/nueva-compra')->assertRedirect('/carrito');
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $newReview = $this->review();
        $this->post('/pedidos/'.$paid->reference.'/nueva-compra')->assertRedirect('/carrito');
        $this->assertSame($newReview, session('checkout.review'));
        $this->post('/checkout', $this->confirmation($newReview))->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(255200, Order::query()->latest('id')->first()->total_cents);
        $this->assertSame(127600, $paid->fresh()->total_cents);
    }

    public function test_guest_reviews_contact_and_total_before_order_exists(): void
    {
        $this->prepareCart();
        $this->get('/checkout')->assertOk()->assertSee('Datos de contacto')->assertSee('name="country"', false);
        $review = $this->review();
        $this->get('/checkout/revision')->assertOk()->assertSee('Revisa tu compra')->assertSee('Persona de prueba')
            ->assertSee('+52 614 123 4567')->assertSee('$1,508.00')->assertDontSee($review['submission_key']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_contact_validation_is_required_without_account_or_address(): void
    {
        $this->prepareCart();
        $this->postJson('/checkout/revision', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone', 'country'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_guest_confirmation_uses_server_contact_prices_key_and_grants_result_access(): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        $this->prepareCart();
        $review = $this->review();
        $response = $this->post('/checkout', $this->confirmation($review) + [
            'name' => 'Contacto falso', 'email' => 'evil@example.test', 'role' => 'installer', 'price' => 1,
            'user_id' => 999, 'submission_key' => 'invented', 'token' => 'invented',
        ])->assertRedirect();
        $order = Order::query()->sole();
        $response->assertRedirect('/pedidos/'.$order->reference);
        $this->assertSame('Persona de prueba', $order->contact_name);
        $this->assertSame('cliente@example.test', $order->contact_email);
        $this->assertSame(150800, $order->total_cents);
        $this->assertNull($order->user_id);
        $this->assertSame($review['submission_key'], $order->paymentAttempts->sole()->submission_key);
        $this->get('/pedidos/'.$order->reference)->assertOk()->assertSee('Pago pendiente');
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_double_submission_preserves_original_order_after_commercial_change(): void
    {
        $product = $this->prepareCart();
        $review = $this->review();
        $this->post('/checkout', $this->confirmation($review))->assertRedirect();
        Product::whereKey($product->id)->update(['base_cost_cents' => 200000, 'is_active' => false]);
        $this->post('/checkout', $this->confirmation($review))->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame(150800, Order::query()->sole()->total_cents);
    }

    public function test_changed_price_requires_review_of_updated_total(): void
    {
        $product = $this->prepareCart();
        $review = $this->review();
        Product::whereKey($product->id)->update(['base_cost_cents' => 200000]);
        $this->postJson('/checkout', $this->confirmation($review))->assertUnprocessable()->assertJsonValidationErrors('accepted_fingerprint');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([['product_id' => $product->id, 'quantity' => 1]], session('cart.lines'));
        $this->get('/checkout/revision')->assertOk()->assertSee('$3,016.00');
        $this->assertNotSame($review['review_key'], session('checkout.review.review_key'));
        $this->post('/checkout', $this->confirmation(session('checkout.review')))->assertRedirect();
        $this->assertSame(301600, Order::query()->sole()->total_cents);
    }

    public function test_old_tab_cannot_accept_new_contact_for_same_quote(): void
    {
        $this->prepareCart();
        $old = $this->review();
        $this->post('/checkout/revision', array_replace($this->contact(), ['name' => 'Otra persona']))->assertRedirect();
        $this->postJson('/checkout', $this->confirmation($old))->assertUnprocessable()->assertJsonValidationErrors('review_key');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changed_cart_requires_new_review(): void
    {
        $product = $this->prepareCart();
        $review = $this->review();
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->postJson('/checkout', $this->confirmation($review))->assertUnprocessable()->assertJsonValidationErrors('accepted_fingerprint');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_terms_acceptance_is_required_and_cannot_be_array(): void
    {
        $this->prepareCart();
        $review = $this->review();
        foreach (['0', ['1']] as $value) {
            $this->postJson('/checkout', array_replace($this->confirmation($review), ['accept_terms' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_browser_cannot_swap_fingerprint(): void
    {
        $this->prepareCart();
        $review = $this->review();
        $this->postJson('/checkout', array_replace($this->confirmation($review), ['accepted_fingerprint' => str_repeat('0', 64)]))
            ->assertUnprocessable()->assertJsonValidationErrors('accepted_fingerprint');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_installer_review_and_snapshot_use_private_tariff(): void
    {
        $this->actingAs(User::factory()->installer()->create());
        $this->prepareCart();
        $review = $this->review();
        $this->get('/checkout/revision')->assertOk()->assertSee('$1,276.00')->assertDontSee('$1,508.00');
        $this->post('/checkout', $this->confirmation($review))->assertRedirect();
        $this->assertSame(127600, Order::query()->sole()->total_cents);
    }

    public function test_suspended_account_is_rejected_before_review_or_confirmation(): void
    {
        $actor = User::factory()->installer()->create();
        $this->actingAs($actor);
        $this->prepareCart();
        $review = $this->review();
        User::whereKey($actor->id)->update(['suspended_at' => now()]);
        $this->getJson('/checkout/revision')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/checkout', $this->confirmation($review))->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_cart_or_missing_review_does_not_create_order(): void
    {
        $this->get('/checkout')->assertRedirect('/carrito');
        $this->get('/checkout/revision')->assertRedirect('/checkout');
        $this->postJson('/checkout', ['accept_terms' => '1', 'accepted_fingerprint' => str_repeat('0', 64), 'review_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('review_key');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_review_escapes_contact_and_product_text(): void
    {
        $product = $this->prepareCart();
        Product::whereKey($product->id)->update(['name' => '<script>alert(1)</script>']);
        $this->post('/checkout/revision', array_replace($this->contact(), ['name' => '<script>alert(2)</script>']))->assertRedirect();
        $this->get('/checkout/revision')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false)->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
    }

    public function test_checkout_post_requires_csrf_outside_test_bypass(): void
    {
        $this->app->instance('env', 'production');
        $this->post('/checkout/revision', $this->contact())->assertStatus(419)->assertHeader('Cache-Control', 'no-store, private');
    }
}
