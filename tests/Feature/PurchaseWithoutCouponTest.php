<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Mail\PurchaseConfirmed;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\StripeEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseWithoutCouponTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.stripe.enabled' => true,
            'services.stripe.secret' => 'sk_test_fixture', 'services.stripe.webhook_secret' => 'whsec_fixture',
            'services.stripe.account_id' => 'acct_fixture', 'services.stripe.livemode' => false, 'store.recovery.cache_store' => 'array']);
        Http::preventStrayRequests();
        Mail::fake();
    }

    /** @return array<string, array{string}> */
    public static function purchaseScenarios(): array
    {
        return ['normal' => ['normal'], 'checkout timeout' => ['checkout_timeout'], 'webhook lookup failure' => ['webhook_failure']];
    }

    #[DataProvider('purchaseScenarios')]
    public function test_guest_completes_purchase_without_coupon(string $scenario): void
    {
        StoreSetting::factory()->create();
        Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $paid = false;
        $failLookup = false;
        $posts = [];
        $expiresAt = now()->addMinutes(30)->timestamp;
        Http::fake(function ($request) use (&$paid, &$failLookup, &$posts, $scenario, $expiresAt) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertContains($request->method().' '.$request->url(), [
                'POST https://api.stripe.com/v1/checkout/sessions',
                'GET https://api.stripe.com/v1/checkout/sessions/cs_test_fixture',
                'GET https://api.stripe.com/v1/account',
                'GET https://api.stripe.com/v1/payment_intents/pi_fixture',
                'GET https://api.stripe.com/v1/charges/ch_fixture',
            ]);
            $attempt = PaymentAttempt::query()->sole();
            $metadata = ['attempt_reference' => $attempt->reference, 'order_reference' => $attempt->order->reference];
            if ($request->method() === 'POST') {
                $posts[] = ['parameters' => $request->data(), 'key' => $request->header('Idempotency-Key')];
                $this->assertSame(150800, $request['line_items'][0]['price_data']['unit_amount']);
                $this->assertEquals($metadata, $request['metadata']);
                $this->assertEquals($metadata, $request['payment_intent_data']['metadata']);
                if ($scenario === 'checkout_timeout' && count($posts) === 1) {
                    throw new ConnectionException('Simulated unknown Checkout response.');
                }
            }
            if ($failLookup) {
                return Http::response(['error' => ['type' => 'api_error']], 503);
            }
            $data = match (true) {
                str_ends_with($request->url(), '/account') => ['id' => 'acct_fixture'],
                str_contains($request->url(), '/payment_intents/') => ['id' => 'pi_fixture', 'object' => 'payment_intent',
                    'status' => 'succeeded', 'amount' => 150800, 'amount_received' => 150800, 'currency' => 'mxn',
                    'livemode' => false, 'metadata' => $metadata, 'latest_charge' => 'ch_fixture'],
                str_contains($request->url(), '/charges/') => ['id' => 'ch_fixture', 'object' => 'charge', 'payment_intent' => 'pi_fixture',
                    'amount' => 150800, 'currency' => 'mxn', 'livemode' => false, 'paid' => true, 'captured' => true,
                    'status' => 'succeeded', 'created' => now()->timestamp],
                default => ['id' => 'cs_test_fixture', 'object' => 'checkout.session', 'mode' => 'payment',
                    'status' => $paid ? 'complete' : 'open', 'payment_status' => $paid ? 'paid' : 'unpaid',
                    'amount_total' => 150800, 'currency' => 'mxn', 'livemode' => false, 'expires_at' => $expiresAt,
                    'payment_intent' => $paid ? 'pi_fixture' : null, 'url' => $paid ? null : 'https://checkout.stripe.com/c/pay/cs_test_fixture',
                    'metadata' => $metadata],
            };

            return Http::response($data);
        });
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->get('/checkout')->assertOk()->assertSee('Datos de contacto');
        $this->post('/checkout/revision', ['name' => 'Cliente de prueba', 'email' => 'cliente@example.test',
            'phone' => '+52 614 123 4567', 'country' => 'MX'])->assertRedirect('/checkout/revision');
        $this->get('/checkout/revision')->assertOk()->assertSee('$1,508.00');
        $this->assertDatabaseCount('orders', 0);
        $review = session('checkout.review');
        $confirmation = ['review_key' => $review['review_key'], 'accepted_fingerprint' => $review['fingerprint'], 'accept_terms' => '1'];
        $this->post('/checkout', $confirmation)->assertRedirect();
        $order = Order::query()->sole();
        $attempt = PaymentAttempt::query()->sole();
        $this->assertNull($order->user_id);
        $this->assertSame('Cliente de prueba', $order->contact_name);
        $this->assertSame('cliente@example.test', $order->contact_email);
        $this->assertSame(130000, $order->subtotal_cents);
        $this->assertSame(0, $order->discount_cents);
        $this->assertSame(20800, $order->tax_cents);
        $this->assertSame(150800, $order->total_cents);
        $this->assertSame(150800, $attempt->amount_cents);
        $snapshot = $order->getRawOriginal();
        $lineSnapshot = $order->lines()->get()->map(fn ($line): array => $line->getRawOriginal())->all();
        if ($scenario === 'checkout_timeout') {
            $this->assertSame(PaymentAttemptStatus::Unknown, $attempt->fresh()->status);
            $this->artisan('store:reconcile-payments')->assertSuccessful();
            $this->assertCount(2, $posts);
            $this->assertSame($posts[0], $posts[1]);
        }
        $resultUrl = route('orders.show', ['order' => $order->reference]);
        $this->get($resultUrl)->assertOk()->assertSee('Pago pendiente')->assertSee('data-stripe-checkout', false);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingOutgoing();
        $this->assertSame([], session('cart.lines'));
        $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $newCart = [['product_id' => $product->id, 'quantity' => 2]];
        $paid = true;
        $body = json_encode(['id' => 'evt_purchase_fixture', 'object' => 'event', 'type' => 'checkout.session.completed',
            'api_version' => config('services.stripe.api_version'), 'livemode' => false, 'created' => now()->timestamp,
            'data' => ['object' => ['id' => 'cs_test_fixture']]], JSON_THROW_ON_ERROR);
        $signature = 't='.now()->timestamp.',v1='.hash_hmac('sha256', now()->timestamp.'.'.$body, 'whsec_fixture');
        $failLookup = $scenario === 'webhook_failure';
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
        if ($failLookup) {
            $this->assertSame('pending', StripeEvent::query()->sole()->status);
            $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
            Mail::assertNothingOutgoing();
            $failLookup = false;
            $this->artisan('store:reconcile-payments')->assertSuccessful();
        }
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
        $this->post('/checkout', $confirmation)->assertRedirect($resultUrl);
        $this->artisan('store:reconcile-payments')->assertSuccessful();
        $this->artisan('store:retry-notifications')->assertSuccessful();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame($newCart, session('cart.lines'));
        $this->assertSame(PaymentAttemptStatus::Paid, $attempt->fresh()->status);
        $this->assertSame('processed', StripeEvent::query()->sole()->status);
        $this->assertSame(NotificationStatus::Accepted, OrderNotification::query()->sole()->status);
        foreach (['orders', 'payment_attempts', 'payments', 'order_notifications', 'notification_attempts'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseCount('financial_incidents', 0);
        $this->assertDatabaseHas('payments', ['stripe_charge_id' => 'ch_fixture', 'amount_cents' => 150800]);
        $this->assertTrue(Schema::hasTable('coupons'));
        $this->assertDatabaseCount('coupons', 1);
        $this->assertFalse(Schema::hasTable('coupon_reservations'));
        foreach ($snapshot as $field => $value) {
            if (! in_array($field, ['status', 'updated_at'], true)) {
                $this->assertSame($value, $order->fresh()->getRawOriginal($field), $field);
            }
        }
        Mail::assertSent(PurchaseConfirmed::class, 1);
        $this->assertSame($lineSnapshot, $order->lines()->get()->map(fn ($line): array => $line->getRawOriginal())->all());
        Mail::assertSent(PurchaseConfirmed::class, fn (PurchaseConfirmed $mail): bool => $mail->hasTo('cliente@example.test'));
        $this->get($resultUrl)->assertOk()->assertDontSee('https://checkout.stripe.com/c/pay/cs_test_fixture');
        $this->assertSame($newCart, session('cart.lines'));
    }
}
