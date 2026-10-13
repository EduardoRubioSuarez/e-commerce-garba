<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\DefinitiveStripeFailure;
use App\Exceptions\UnknownStripeOutcome;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use DatabaseMigrations;

    public function test_replacement_refuses_another_potentially_payable_attempt_on_same_order(): void
    {
        $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Failed]);
        PaymentAttempt::factory()->create(['order_id' => $attempt->order_id, 'status' => PaymentAttemptStatus::Open]);
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->resolveBeforeReplacement($attempt);
    }

    public function test_owner_can_resume_unknown_attempt_without_new_order_and_others_are_denied(): void
    {
        $owner = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        $attempt = PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => PaymentAttemptStatus::Unknown,
            'first_sent_at' => now(), 'checkout_parameters' => ['mode' => 'payment']]);
        $url = '/pedidos/'.$order->reference.'/pago';
        $this->actingAs(User::factory()->installer()->create())->post($url)->assertNotFound();
        Http::fake(['*' => Http::response($this->remoteSession($attempt))]);
        $this->actingAs($owner)->post($url)->assertRedirect(route('orders.show', ['order' => $order->reference]));
        $this->assertSame(PaymentAttemptStatus::Open, $attempt->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_concurrent_ip_limit_claim_fails_closed_before_order_creation(): void
    {
        $lock = Cache::lock('checkout:limits:ip:'.hash('sha256', '127.0.0.1'), 10);
        $this->assertTrue($lock->get());
        try {
            $this->postJson('/checkout', [])->assertStatus(429);
        } finally {
            $lock->release();
        }
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_sixth_new_attempt_in_one_hour_is_blocked_without_persisting_another_order(): void
    {
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/expire')) {
                preg_match('~/sessions/([^/]+)/expire$~', $request->url(), $matches);
                $attempt = PaymentAttempt::query()->where('stripe_session_id', $matches[1])->firstOrFail();
                $data = $this->remoteSession($attempt);
                $data['id'] = $attempt->stripe_session_id;
                $data['status'] = 'expired';
                $data['url'] = null;
            } else {
                $attempt = PaymentAttempt::query()->where('reference', $request['metadata']['attempt_reference'])->firstOrFail();
                $data = $this->remoteSession($attempt);
                $data['id'] = 'cs_test_'.$attempt->id;
            }

            return Http::response($data);
        });
        for ($i = 0; $i < 6; $i++) {
            $this->put('/carrito', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
            $this->post('/checkout/revision', ['name' => 'Cliente', 'email' => 'cliente'.$i.'@example.test', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
            $review = session('checkout.review');
            $response = $this->post('/checkout', ['review_key' => $review['review_key'], 'accepted_fingerprint' => $review['fingerprint'], 'accept_terms' => '1']);
            if ($i < 5) {
                $response->assertRedirect();
            } else {
                $response->assertStatus(429);
            }
        }
        $this->assertDatabaseCount('orders', 5);
        $this->assertDatabaseCount('payment_attempts', 5);
    }

    public function test_concurrent_unknown_cannot_be_overwritten_by_stale_definitive_failure(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(function () use ($attempt) {
            $fresh = $attempt->fresh();
            $fresh->status = PaymentAttemptStatus::Unknown;
            $fresh->save();

            return Http::response(['error' => ['type' => 'invalid_request_error']], 400);
        });
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Expected rejection');
        } catch (DefinitiveStripeFailure|UnknownStripeOutcome) {
        }
        $this->assertSame(PaymentAttemptStatus::Unknown, $attempt->fresh()->status);
    }

    public function test_replacement_requires_expired_unpaid_remote_evidence_and_preserves_snapshot(): void
    {
        $attempt = PaymentAttempt::factory()->create(['stripe_session_id' => 'cs_test_fixture', 'status' => PaymentAttemptStatus::Open]);
        $snapshot = $attempt->order->getAttributes();
        $fixture = $this->remoteSession($attempt);
        $fixture['status'] = 'expired';
        $fixture['url'] = null;
        Http::fake(['*' => Http::response($fixture)]);
        app(CheckoutService::class)->resolveBeforeReplacement($attempt);
        $this->assertSame(OrderStatus::ClosedUnpaid, $attempt->order->fresh()->status);
        foreach (['total_cents', 'contact_email', 'fingerprint'] as $field) {
            $this->assertSame($snapshot[$field], $attempt->order->fresh()->getAttribute($field));
        }
    }

    public function test_payment_winning_expiration_blocks_replacement(): void
    {
        $attempt = PaymentAttempt::factory()->create(['stripe_session_id' => 'cs_test_fixture', 'status' => PaymentAttemptStatus::Open]);
        $fixture = $this->remoteSession($attempt);
        $fixture['status'] = 'complete';
        $fixture['payment_status'] = 'paid';
        $fixture['payment_intent'] = 'pi_fixture';
        Http::fake(['*' => Http::response($fixture)]);
        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->resolveBeforeReplacement($attempt);
    }

    public function test_request_abuse_does_not_persist_orders(): void
    {
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/checkout', [])->assertUnprocessable();
        }
        $this->postJson('/checkout', [])->assertStatus(429);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('uncertainStatuses')]
    public function test_unknown_beyond_safe_window_never_posts_again_even_when_list_is_empty(PaymentAttemptStatus $initialStatus): void
    {
        $attempt = PaymentAttempt::factory()->create(['status' => $initialStatus, 'first_sent_at' => now()->subHours(23), 'checkout_parameters' => ['mode' => 'payment']]);
        Http::fake(['*' => Http::response(['object' => 'list', 'data' => [], 'has_more' => false])]);
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Expected unresolved outcome');
        } catch (UnknownStripeOutcome) {
        }
        $this->assertSame(PaymentAttemptStatus::Unknown, $attempt->fresh()->status);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public static function uncertainStatuses(): array
    {
        return [[PaymentAttemptStatus::Unknown], [PaymentAttemptStatus::Creating]];
    }

    public function test_expiration_uses_remote_evidence_and_never_marks_paid(): void
    {
        $attempt = PaymentAttempt::factory()->create(['stripe_session_id' => 'cs_test_fixture', 'status' => PaymentAttemptStatus::Open]);
        $fixture = $this->remoteSession($attempt);
        $fixture['status'] = 'expired';
        $fixture['url'] = null;
        Http::fake(['*' => Http::response($fixture)]);
        $session = app(StripeGateway::class)->expireCheckout('cs_test_fixture');
        app(StripeGateway::class)->remember($attempt, $session);
        $this->assertSame(PaymentAttemptStatus::Open, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test_fixture', 'services.stripe.account_id' => 'acct_fixture', 'services.stripe.enabled' => true]);
        Http::preventStrayRequests();
    }

    /** @return array<string,mixed> */
    private function remoteSession(PaymentAttempt $attempt): array
    {
        return ['id' => 'cs_test_fixture', 'object' => 'checkout.session', 'livemode' => false,
            'mode' => 'payment', 'status' => 'open', 'payment_status' => 'unpaid',
            'amount_total' => $attempt->amount_cents, 'currency' => 'mxn', 'payment_intent' => null,
            'metadata' => ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference],
            'client_reference_id' => $attempt->reference, 'expires_at' => now()->addMinutes(30)->timestamp,
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_fixture'];
    }

    public function test_creation_persists_exact_server_parameters_and_reuses_session_without_locks(): void
    {
        $this->freezeTime();
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(function ($request) use ($attempt) {
            $this->assertSame(0, DB::transactionLevel());
            $stored = $attempt->fresh();
            $this->assertNotNull($stored->first_sent_at);
            $this->assertSame($stored->checkout_parameters, $request->data());
            $this->assertSame($attempt->idempotency_key, $request->header('Idempotency-Key')[0]);
            $this->assertSame('2026-08-26.dahlia', $request->header('Stripe-Version')[0]);
            $this->assertSame('payment', $request['mode']);
            $this->assertSame(['card'], $request['payment_method_types']);
            $this->assertSame($attempt->amount_cents, $request['line_items'][0]['price_data']['unit_amount']);
            $this->assertSame('mxn', $request['line_items'][0]['price_data']['currency']);
            $this->assertSame('false', $request['automatic_tax']['enabled']);
            $this->assertSame('false', $request['allow_promotion_codes']);
            $this->assertSame(now()->addMinutes(31)->timestamp, $request['expires_at']);
            $this->assertSame('embedded_page', $request['ui_mode']);
            $this->assertSame('never', $request['redirect_on_completion']);
            $this->assertArrayNotHasKey('success_url', $request->data());

            return Http::response($this->remoteSession($attempt));
        });
        $gateway = app(StripeGateway::class);
        $first = $gateway->createCheckout($attempt);
        $this->travel(5)->minutes();
        $second = $gateway->createCheckout($attempt);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(PaymentAttemptStatus::Open, $attempt->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_timeout_preserves_unknown_parameters_and_key_on_retry(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(fn () => throw new ConnectionException('fixture timeout'));
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Expected unknown outcome');
        } catch (UnknownStripeOutcome) {
        }
        $original = $attempt->fresh()->checkout_parameters;
        $this->assertSame(PaymentAttemptStatus::Unknown, $attempt->fresh()->status);
        Http::swap(new Factory);
        Http::fake(fn () => Http::response($this->remoteSession($attempt)));
        app(StripeGateway::class)->createCheckout($attempt);
        $this->assertSame($original, $attempt->fresh()->checkout_parameters);
    }

    public function test_checkout_expiration_survives_transport_delay_before_stripe_receives_request(): void
    {
        $this->freezeTime();
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(function ($request) use ($attempt) {
            $this->travel(5)->seconds();
            if ($request['expires_at'] < now()->addMinutes(30)->timestamp) {
                return Http::response(['error' => ['type' => 'invalid_request_error']], 400);
            }

            $session = $this->remoteSession($attempt);
            $session['expires_at'] = $request['expires_at'];

            return Http::response($session);
        });

        $session = app(StripeGateway::class)->createCheckout($attempt);

        $this->assertSame('open', $session->status);
        $this->assertSame(PaymentAttemptStatus::Open, $attempt->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_definitive_rejection_is_failed(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(['*' => Http::response(['error' => ['type' => 'invalid_request_error']], 400)]);
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Expected rejection');
        } catch (DefinitiveStripeFailure) {
        }
        $this->assertSame(PaymentAttemptStatus::Failed, $attempt->fresh()->status);
    }

    public function test_external_transaction_is_rejected_before_network(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        DB::beginTransaction();
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Expected lock guard');
        } catch (\LogicException) {
        } finally {
            DB::rollBack();
        }
        Http::assertNothingSent();
    }

    public function test_guest_double_confirmation_calls_stripe_after_controller_commit_and_issues_one_token(): void
    {
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $this->withSession(['cart.lines' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->post('/checkout/revision', ['name' => 'Cliente', 'email' => 'cliente@example.test', 'phone' => '6141234567', 'country' => 'MX'])->assertRedirect();
        $review = session('checkout.review');
        Http::fake(function () {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response($this->remoteSession(PaymentAttempt::query()->sole()));
        });
        $input = ['review_key' => $review['review_key'], 'accepted_fingerprint' => $review['fingerprint'], 'accept_terms' => '1'];
        $this->post('/checkout', $input)->assertRedirect();
        $order = Order::query()->sole();
        $hash = $order->token_hash;
        $this->post('/checkout', $input)->assertRedirect();
        $this->assertSame($hash, $order->fresh()->token_hash);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->get(route('orders.show', ['order' => $order->reference]))->assertOk()->assertSee('data-stripe-checkout', false);
        Http::assertSentCount(1);
    }
}
