<?php

namespace Tests\Feature;

use App\Data\VerifiedPaymentResult;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\InstallerInvitationService;
use App\Services\PaymentResultApplier;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreAcceptanceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.stripe.enabled' => true,
            'store.recovery.cache_store' => 'array']);
        StoreSetting::factory()->create();
        Http::preventStrayRequests();
        Mail::fake();
    }

    /** @return array<string, array{bool, bool, int}> */
    public static function purchases(): array
    {
        return ['guest without coupon' => [false, false, 150800], 'guest promo10' => [false, true, 135720],
            'installer excluded' => [true, false, 127600], 'installer enabled promo10' => [true, true, 114840]];
    }

    #[DataProvider('purchases')]
    public function test_purchase_review_recovery_pickup_refund_and_access_form_one_consistent_flow(bool $private, bool $discount, int $total): void
    {
        $installer = $private ? User::factory()->installer()->create() : null;
        if ($installer !== null) {
            $this->actingAs($installer);
        }
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon = Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000, 'applies_to_installers' => $discount]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        if ($discount || $private) {
            $response = $this->postJson('/checkout/cupon', ['coupon_code' => 'PROMO10', 'phone' => '6141234567', 'country' => 'MX']);
            if ($discount) {
                $response->assertOk()->assertJsonPath('data.total_cents', $total);
            } else {
                $response->assertUnprocessable();
            }
        }
        $this->post('/checkout/revision', ['name' => 'Acceptance contact', 'email' => 'acceptance@example.test', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect('/checkout/revision');
        $review = session('checkout.review');
        $this->get('/checkout/revision')->assertOk()->assertSee(Money::format($total));
        $paid = false;
        Http::fake(function ($request) use (&$paid, $total) {
            $this->assertSame(0, DB::transactionLevel());
            $attempt = PaymentAttempt::query()->sole();
            $metadata = ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference];
            if ($request->method() === 'POST') {
                $this->assertSame($total, $request['line_items'][0]['price_data']['unit_amount']);
                $this->assertSame($attempt->idempotency_key, $request->header('Idempotency-Key')[0]);
            }

            return Http::response(match (true) {
                str_ends_with($request->url(), '/account') => ['id' => 'acct_fixture'],
                str_contains($request->url(), '/payment_intents/') => ['id' => 'pi_acceptance', 'object' => 'payment_intent',
                    'status' => 'succeeded', 'amount' => $total, 'amount_received' => $total, 'currency' => 'mxn', 'livemode' => false, 'metadata' => $metadata, 'latest_charge' => 'ch_acceptance'],
                str_contains($request->url(), '/charges/') => ['id' => 'ch_acceptance', 'object' => 'charge', 'payment_intent' => 'pi_acceptance',
                    'amount' => $total, 'currency' => 'mxn', 'livemode' => false, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'created' => now()->timestamp],
                default => ['id' => 'cs_test_acceptance', 'object' => 'checkout.session', 'mode' => 'payment', 'status' => $paid ? 'complete' : 'open',
                    'payment_status' => $paid ? 'paid' : 'unpaid', 'amount_total' => $total, 'currency' => 'mxn', 'livemode' => false, 'metadata' => $metadata,
                    'expires_at' => now()->addMinutes(30)->timestamp, 'payment_intent' => $paid ? 'pi_acceptance' : null,
                    'url' => $paid ? null : 'https://checkout.stripe.com/c/pay/cs_test_acceptance'],
            });
        });
        $confirmation = ['review_key' => $review['review_key'], 'accepted_fingerprint' => $review['fingerprint'], 'accept_terms' => '1',
            'total_cents' => 1, 'tier' => 'administrator', 'user_id' => 999];
        $this->post('/checkout', $confirmation)->assertRedirect();
        $order = Order::query()->sole();
        $attempt = PaymentAttempt::query()->sole();
        $this->assertSame($installer?->id, $order->user_id);
        $this->assertSame($total, $order->total_cents);
        $this->assertSame($private ? PriceTier::Installer : PriceTier::Public, $order->tier);
        $this->get('/pedidos/'.$order->reference)->assertOk()->assertSee('Pago pendiente');
        $this->post('/checkout', $confirmation)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);
        $paid = true;
        $this->assertFalse((bool) config('services.stripe.livemode'));
        $this->post('/admin/payments/'.$attempt->id.'/recover')->assertRedirect('/admin/orders/'.$order->reference)->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status, json_encode(['incidents' => DB::table('financial_incidents')->pluck('kind')->all(), 'attempt_status' => $attempt->fresh()->status->value]));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertSame(NotificationStatus::Accepted, OrderNotification::query()->sole()->status);
        $original = $order->fresh()->getRawOriginal();
        $this->post('/admin/orders/'.$order->reference.'/pickup-notification')->assertRedirect();
        $this->post('/admin/orders/'.$order->reference.'/pickup-notification')->assertRedirect();
        $this->assertDatabaseCount('order_notifications', 2);
        $this->assertDatabaseCount('notification_attempts', 2);
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult($attempt->reference, $order->reference,
            'fixture', 'evt_full_refund', 'acct_fixture', false, 'MXN', $total, 'paid', 'cs_test_acceptance', 'pi_acceptance', 'ch_acceptance', now()->timestamp,
            refunds: [['id' => 're_acceptance', 'amount' => $total, 'status' => 'succeeded', 'created' => now()->timestamp]]));
        $this->assertSame($original, $order->fresh()->getRawOriginal());
        $this->get('/admin/orders')->assertOk()->assertSee($order->reference);
        $this->get('/admin/orders/'.$order->reference)->assertOk()->assertSeeInOrder(['Neto', '$0.00'])->assertSee('fully_refunded');
        $this->postJson('/admin/orders/'.$order->reference.'/pickup-notification/resend', ['intention_key' => (string) Str::uuid(),
            'confirm_resend' => '1', 'confirm_possible_duplicate' => '1'])->assertUnprocessable();
        if ($discount) {
            $this->assertSame('consumed', CouponUse::query()->sole()->status->value);
        } else {
            $this->assertDatabaseCount('coupon_uses', 0);
        }
        if ($installer !== null) {
            app(InstallerInvitationService::class)->suspend($installer, $admin);
            $this->actingAs($installer)->getJson('/pedidos/'.$order->reference)->assertNotFound();
            $this->assertSame(1, Payment::query()->count());
        }
    }
}
