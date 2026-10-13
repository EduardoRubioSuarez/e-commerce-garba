<?php

namespace Tests\Feature;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Data\VerifiedPaymentResult;
use App\Enums\PriceTier;
use App\Exceptions\DefinitiveStripeFailure;
use App\Exceptions\UnknownStripeOutcome;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CheckoutService;
use App\Services\PaymentReconciler;
use App\Services\PaymentResultApplier;
use App\Services\PricingService;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponCheckoutRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'services.stripe.secret' => 'sk_test_fixture', 'services.stripe.account_id' => 'acct_fixture',
            'services.stripe.livemode' => false]);
        StoreSetting::factory()->create();
        Http::preventStrayRequests();
        Mail::fake();
    }

    /** @return array{Coupon, Product, PaymentAttempt, string} */
    private function purchase(string $phone = '6141234567', string $canonical = '+526141234567', ?Coupon $coupon = null): array
    {
        $coupon ??= Coupon::factory()->create(['code' => 'PROMO10', 'value' => 1000]);
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $key = (string) Str::uuid();
        $attempt = app(CheckoutService::class)->start($lines, new ContactData('Prueba', 'fixture@example.test', $phone, $canonical), null, $quote->fingerprint, $key, $coupon->code);

        return [$coupon, $product, $attempt, $key];
    }

    private function paid(PaymentAttempt $attempt, array $refunds = []): VerifiedPaymentResult
    {
        return new VerifiedPaymentResult($attempt->reference, $attempt->order->reference, 'stripe_api', 'evt_coupon',
            'acct_fixture', false, $attempt->currency, $attempt->amount_cents, 'paid', $attempt->stripe_session_id,
            'pi_coupon_'.$attempt->id, 'ch_coupon_'.$attempt->id, refunds: $refunds);
    }

    public function test_retry_keeps_discount_snapshot_key_and_attempt_after_coupon_and_catalog_edits(): void
    {
        [$coupon, $product, $attempt, $key] = $this->purchase();
        $snapshot = $attempt->order->getRawOriginal();
        $parameters = ['original' => 'fixture'];
        $attempt->forceFill(['status' => 'unknown', 'first_sent_at' => now(), 'checkout_parameters' => $parameters])->save();
        $coupon->update(['value' => 2000, 'is_active' => false]);
        $product->update(['base_cost_cents' => 200000, 'is_active' => false]);
        $retry = app(CheckoutService::class)->start([new CartLineData($product->id, 1)], new ContactData('Prueba', 'fixture@example.test', '6141234567', '+526141234567'),
            null, $attempt->order->fingerprint, $key, 'PROMO10');
        $this->assertSame($attempt->id, $retry->id);
        $this->assertSame($attempt->idempotency_key, $retry->idempotency_key);
        $this->assertSame(135720, $retry->amount_cents);
        $this->assertSame($parameters, $retry->checkout_parameters);
        $this->assertSame($snapshot, $retry->order->getRawOriginal());
        $this->assertDatabaseCount('coupon_uses', 1);
        $this->assertDatabaseCount('coupon_use_histories', 1);
    }

    public function test_partial_and_full_refunds_preserve_consumed_use_and_one_purchase_notice(): void
    {
        [, , $attempt] = $this->purchase();
        $applier = app(PaymentResultApplier::class);
        $applier->apply($attempt, $this->paid($attempt));
        $first = ['id' => 're_coupon_partial', 'amount' => 35720, 'status' => 'succeeded', 'created' => now()->timestamp];
        $second = ['id' => 're_coupon_remaining', 'amount' => 100000, 'status' => 'succeeded', 'created' => now()->timestamp];
        $applier->apply($attempt, $this->paid($attempt, [$first]));
        $applier->apply($attempt, $this->paid($attempt, [$first, $second]));
        $applier->apply($attempt, $this->paid($attempt, [$first, $second]));
        $this->assertSame('consumed', CouponUse::query()->sole()->status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('refunds', 2);
        $this->assertSame(135720, (int) DB::table('refunds')->sum('amount_cents'));
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('coupon_use_histories', 2);
        $this->assertSame('paid', $attempt->order->fresh()->status->value);
    }

    public function test_late_payment_after_verified_expiration_does_not_consume_reused_coupon(): void
    {
        [$coupon, , $first] = $this->purchase();
        app(PaymentResultApplier::class)->apply($first, new VerifiedPaymentResult($first->reference, $first->order->reference,
            'stripe_api', 'evt_expired', 'acct_fixture', false, $first->currency, $first->amount_cents, 'expired'));
        [, , $next] = $this->purchase(coupon: $coupon);
        app(PaymentResultApplier::class)->apply($first->fresh(), $this->paid($first));
        $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
        $this->assertSame($next->id, CouponUse::query()->sole()->payment_attempt_id);
        $this->assertSame('paid', $first->order->fresh()->status->value);
        $this->assertSame(135720, $first->order->fresh()->total_cents);
        $this->assertDatabaseHas('financial_incidents', ['kind' => 'coupon_reservation_conflict']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_different_name_and_equivalent_phone_cannot_get_second_discount(): void
    {
        [$coupon, $product, $attempt] = $this->purchase();
        app(PaymentResultApplier::class)->apply($attempt, $this->paid($attempt));
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        try {
            app(CheckoutService::class)->start($lines, new ContactData('Otro nombre', 'other@example.test', '+52 614 123 4567', '+526141234567'),
                null, $quote->fingerprint, (string) Str::uuid(), 'PROMO10');
            $this->fail('A name change must not grant another coupon use.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('payment_attempts', 1);
            $this->assertSame('consumed', CouponUse::query()->sole()->status->value);
        }
    }

    public function test_timeout_retains_reservation_and_retries_same_parameters_outside_locks(): void
    {
        [$coupon, , $attempt] = $this->purchase();
        $firstParameters = null;
        $calls = 0;
        Http::fake(function ($request) use ($attempt, &$firstParameters, &$calls) {
            $this->assertSame(0, DB::transactionLevel());
            $calls++;
            if ($calls === 1) {
                $firstParameters = $request->data();
                throw new ConnectionException('Simulated unknown outcome.');
            }
            $this->assertSame($firstParameters, $request->data());
            $this->assertSame([$attempt->idempotency_key], $request->header('Idempotency-Key'));

            return Http::response(['id' => 'cs_test_coupon', 'object' => 'checkout.session', 'mode' => 'payment', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_coupon',
                'status' => 'open', 'payment_status' => 'unpaid', 'amount_total' => 135720, 'currency' => 'mxn',
                'livemode' => false, 'payment_intent' => null, 'expires_at' => $attempt->fresh()->expires_at->timestamp,
                'metadata' => ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference]]);
        });
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Timeout must remain unknown.');
        } catch (UnknownStripeOutcome) {
            $this->assertSame('unknown', $attempt->fresh()->status->value);
            $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
        }
        $coupon->update(['is_active' => false, 'value' => 2000]);
        app(StripeGateway::class)->createCheckout($attempt->fresh());
        $this->assertSame(2, $calls);
        $this->assertSame('open', $attempt->fresh()->status->value);
        $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame(135720, $attempt->fresh()->amount_cents);
    }

    public function test_rejection_of_previously_sent_creating_attempt_is_unknown_and_keeps_reservation(): void
    {
        [, , $attempt] = $this->purchase();
        $attempt->forceFill(['first_sent_at' => now(), 'checkout_parameters' => ['mode' => 'payment']])->save();
        Http::fake(['*' => Http::response(['error' => ['type' => 'invalid_request_error']], 400)]);
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('A previous remote request requires reconciliation.');
        } catch (UnknownStripeOutcome) {
            $this->assertSame('unknown', $attempt->fresh()->status->value);
            $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
            $this->assertDatabaseCount('payments', 0);
            $this->assertSame('pending', $attempt->order->fresh()->status->value);
        }
    }

    public function test_concurrent_unknown_wins_over_stale_definitive_rejection(): void
    {
        [, , $attempt] = $this->purchase();
        Http::fake(function () use ($attempt) {
            $attempt->fresh()->forceFill(['status' => 'unknown'])->save();

            return Http::response(['error' => ['type' => 'invalid_request_error']], 400);
        });
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Rejection must be reported.');
        } catch (DefinitiveStripeFailure) {
            $this->assertSame('unknown', $attempt->fresh()->status->value);
            $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
            $this->assertDatabaseCount('payments', 0);
        }
    }

    public function test_definitive_remote_rejection_releases_without_inventing_missing_payment(): void
    {
        [, , $attempt] = $this->purchase();
        Http::fake(function () {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response(['error' => ['type' => 'invalid_request_error']], 400);
        });
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('Stripe rejection must be reported.');
        } catch (DefinitiveStripeFailure) {
            $this->assertSame('failed', $attempt->fresh()->status->value);
            $this->assertSame('free', CouponUse::query()->sole()->status->value);
            $this->assertSame('closed_unpaid', $attempt->order->fresh()->status->value);
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('coupon_use_histories', 2);
        }
    }

    public function test_resuming_expired_checkout_without_webhook_keeps_verified_recovery_eligible(): void
    {
        [, , $attempt] = $this->purchase();
        config(['store.recovery.cache_store' => 'array']);
        $attempt->forceFill(['status' => 'open', 'stripe_session_id' => 'cs_test_expired_coupon',
            'expires_at' => now()->subMinute(), 'first_sent_at' => now()->subHour(), 'checkout_parameters' => ['mode' => 'payment']])->save();
        Http::fake([
            'https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_fixture']),
            'https://api.stripe.com/v1/checkout/sessions/*' => Http::response([
                'id' => 'cs_test_expired_coupon', 'object' => 'checkout.session', 'mode' => 'payment', 'url' => null,
                'status' => 'expired', 'payment_status' => 'unpaid', 'amount_total' => 135720, 'currency' => 'mxn',
                'livemode' => false, 'payment_intent' => null, 'expires_at' => $attempt->expires_at->timestamp,
                'metadata' => ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference],
            ]),
        ]);
        app(StripeGateway::class)->createCheckout($attempt);
        $this->assertSame('open', $attempt->fresh()->status->value);
        $this->assertSame('reserved', CouponUse::query()->sole()->status->value);
        $this->assertSame(1, app(PaymentReconciler::class)->run(1));
        $this->assertSame('expired', $attempt->fresh()->status->value);
        $this->assertSame('closed_unpaid', $attempt->order->fresh()->status->value);
        $this->assertSame('free', CouponUse::query()->sole()->status->value);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('order_notifications', 0);
    }

    public function test_replacement_of_coupon_attempt_does_not_bypass_common_verified_expiration(): void
    {
        [, , $attempt] = $this->purchase();
        $attempt->forceFill(['status' => 'open', 'stripe_session_id' => 'cs_test_expired_coupon',
            'expires_at' => now()->subMinute(), 'first_sent_at' => now()->subHour(), 'checkout_parameters' => ['mode' => 'payment']])->save();
        Http::fake([
            'https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_fixture']),
            'https://api.stripe.com/v1/checkout/sessions/*' => Http::response([
                'id' => 'cs_test_expired_coupon', 'object' => 'checkout.session', 'mode' => 'payment', 'url' => null,
                'status' => 'expired', 'payment_status' => 'unpaid', 'amount_total' => 135720, 'currency' => 'mxn',
                'livemode' => false, 'payment_intent' => null, 'expires_at' => $attempt->expires_at->timestamp,
                'metadata' => ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference],
            ]),
        ]);
        app(CheckoutService::class)->resolveBeforeReplacement($attempt);
        $this->assertSame('expired', $attempt->fresh()->status->value);
        $this->assertSame('free', CouponUse::query()->sole()->status->value);
        $this->assertSame('closed_unpaid', $attempt->order->fresh()->status->value);
        $this->assertDatabaseCount('payments', 0);
    }
}
