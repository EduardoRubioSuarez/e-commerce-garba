<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\NotificationAttempt;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\PaymentAttempt;
use App\Models\StripeEvent;
use App\Services\PaymentReconciler;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentRecoveryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_unresolvable_event_does_not_starve_attempt_when_limit_is_one(): void
    {
        StripeEvent::factory()->create(['type' => 'checkout.session.completed', 'payload' => ['data' => ['object' => ['id' => 'invalid']]]]);
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(['*' => Http::response($this->remoteSession($attempt))]);
        $this->assertSame(0, app(PaymentReconciler::class)->run(1));
        $this->assertSame(1, app(PaymentReconciler::class)->run(1));
        $this->assertSame('cs_test_fixture', $attempt->fresh()->stripe_session_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test_fixture', 'services.stripe.account_id' => 'acct_fixture',
            'store.recovery.cache_store' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    /** @return array<string,mixed> */
    private function remoteSession(PaymentAttempt $attempt, bool $paid = false): array
    {
        return ['id' => 'cs_test_fixture', 'object' => 'checkout.session', 'mode' => 'payment', 'status' => $paid ? 'complete' : 'open',
            'payment_status' => $paid ? 'paid' : 'unpaid', 'amount_total' => $attempt->amount_cents, 'currency' => 'mxn', 'livemode' => false,
            'expires_at' => now()->addMinutes(30)->timestamp, 'payment_intent' => $paid ? 'pi_fixture' : null,
            'url' => $paid ? null : 'https://checkout.stripe.com/c/pay/cs_test_fixture',
            'metadata' => ['attempt_reference' => $attempt->reference, 'order_reference' => $attempt->order->reference]];
    }

    public function test_crash_before_http_is_recovered_with_original_attempt(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(['*' => Http::response($this->remoteSession($attempt))]);
        $this->assertSame(1, app(PaymentReconciler::class)->run(25));
        $this->assertSame('cs_test_fixture', $attempt->fresh()->stripe_session_id);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_session_created_before_local_id_uses_identical_parameters_and_key(): void
    {
        $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Unknown, 'first_sent_at' => now()->subMinute(),
            'checkout_parameters' => ['mode' => 'payment', 'expires_at' => now()->addMinutes(29)->timestamp]]);
        $original = $attempt->checkout_parameters;
        Http::fake(function ($request) use ($attempt, $original) {
            $this->assertSame($original, $request->data());
            $this->assertSame($attempt->idempotency_key, $request->header('Idempotency-Key')[0]);

            return Http::response($this->remoteSession($attempt));
        });
        app(PaymentReconciler::class)->run(25);
        $this->assertSame('cs_test_fixture', $attempt->fresh()->stripe_session_id);
    }

    public function test_persisted_event_is_recovered_and_paid_outbox_is_sent(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $session = $this->remoteSession($attempt, true);
        $intent = ['id' => 'pi_fixture', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => $attempt->amount_cents,
            'amount_received' => $attempt->amount_cents, 'currency' => 'mxn', 'livemode' => false, 'metadata' => $session['metadata'], 'latest_charge' => 'ch_fixture'];
        $charge = ['id' => 'ch_fixture', 'object' => 'charge', 'payment_intent' => 'pi_fixture', 'amount' => $attempt->amount_cents,
            'currency' => 'mxn', 'livemode' => false, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'created' => now()->timestamp];
        Http::fake(['https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_fixture']),
            'https://api.stripe.com/v1/payment_intents/pi_fixture' => Http::response($intent),
            'https://api.stripe.com/v1/charges/ch_fixture' => Http::response($charge),
            '*' => Http::response($session)]);
        $event = StripeEvent::factory()->create(['type' => 'checkout.session.completed', 'payload' => [
            'type' => 'checkout.session.completed', 'api_version' => '2026-08-26.dahlia', 'livemode' => false, 'created' => now()->timestamp, 'data' => ['object' => $session]]]);
        app(PaymentReconciler::class)->run(25);
        $this->assertSame('processed', $event->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
        $this->assertSame(NotificationStatus::Accepted, OrderNotification::query()->sole()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_paid_before_smtp_is_recovered_by_notification_command(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $notification = OrderNotification::factory()->create(['order_id' => $order->id]);
        $this->artisan('store:retry-notifications', ['--limit' => 25])->assertSuccessful();
        $this->assertSame(NotificationStatus::Accepted, $notification->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_exhausted_idempotency_window_is_unknown_without_post(): void
    {
        $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Unknown, 'first_sent_at' => now()->subHours(24), 'checkout_parameters' => ['mode' => 'payment']]);
        Http::fake(['*' => Http::response(['object' => 'list', 'data' => [], 'has_more' => false])]);
        app(PaymentReconciler::class)->run(25);
        $this->assertSame(PaymentAttemptStatus::Unknown, $attempt->fresh()->status);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertSame(1, Cache::store('array')->get('store:operations:payments')['errors']);
    }

    public function test_batch_failure_does_not_starve_next_pending_attempt(): void
    {
        $first = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Unknown, 'first_sent_at' => now()->subDays(2), 'checkout_parameters' => ['mode' => 'payment']]);
        $second = PaymentAttempt::factory()->create();
        Http::fake(function ($request) use ($second) {
            if ($request->method() === 'GET') {
                return Http::response(['object' => 'list', 'data' => [], 'has_more' => false]);
            }

            return Http::response($this->remoteSession($second));
        });
        $this->assertSame(0, app(PaymentReconciler::class)->run(1));
        $this->assertSame(1, app(PaymentReconciler::class)->run(1));
        $this->assertSame('cs_test_fixture', $second->fresh()->stripe_session_id);
        $this->assertSame(PaymentAttemptStatus::Unknown, $first->fresh()->status);
    }

    public function test_paid_attempts_are_not_scanned_for_missing_refunds(): void
    {
        PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Paid]);
        $this->assertSame(0, app(PaymentReconciler::class)->run(25));
        Http::assertNothingSent();
    }

    public function test_abandoned_prepared_claim_recovers_without_blindly_replaying_sending_claim(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $notification = OrderNotification::factory()->create(['order_id' => $order->id, 'status' => NotificationStatus::Claimed,
            'claim_token' => '00000000-0000-4000-8000-000000000001', 'lease_expires_at' => now()->subMinute()]);
        NotificationAttempt::factory()->create(['order_notification_id' => $notification->id, 'claim_token' => $notification->claim_token, 'status' => 'prepared']);
        $this->artisan('store:retry-notifications')->assertSuccessful();
        $this->assertSame(NotificationStatus::Accepted, $notification->fresh()->status);
        $this->assertDatabaseCount('notification_attempts', 2);
    }

    public function test_limits_and_time_budget_prevent_unbounded_network(): void
    {
        PaymentAttempt::factory()->create();
        config(['store.recovery.budget_seconds' => 0]);
        $this->assertSame(0, app(PaymentReconciler::class)->run(25));
        Http::assertNothingSent();
        $this->artisan('store:reconcile-payments', ['--limit' => 0])->assertExitCode(2);
        $this->artisan('store:retry-notifications', ['--limit' => 101])->assertExitCode(2);
    }
}
