<?php

namespace Tests\Feature;

use App\Data\VerifiedPaymentResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Mail\PurchaseConfirmed;
use App\Models\FinancialIncident;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\StripeEvent;
use App\Services\PaymentResultApplier;
use App\Services\PaymentResultResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentTransitionsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test_fixture', 'services.stripe.account_id' => 'acct_fixture', 'services.stripe.webhook_secret' => 'whsec_fixture',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    /** @param array<string,mixed> $changes */
    private function fixture(PaymentAttempt $attempt, string $suffix = 'fixture', array $changes = []): StripeEvent
    {
        $metadata = ['attempt_reference' => $attempt->reference, 'order_reference' => $attempt->order->reference];
        $session = ['object' => 'checkout.session', 'id' => 'cs_test_'.$suffix, 'mode' => 'payment', 'amount_total' => $attempt->amount_cents,
            'currency' => 'mxn', 'livemode' => false, 'status' => 'complete', 'payment_status' => 'paid', 'payment_intent' => 'pi_'.$suffix,
            'expires_at' => now()->addMinutes(30)->timestamp, 'url' => null, 'metadata' => $metadata];
        $intent = array_replace(['object' => 'payment_intent', 'id' => 'pi_'.$suffix, 'amount' => $attempt->amount_cents, 'amount_received' => $attempt->amount_cents,
            'currency' => 'mxn', 'livemode' => false, 'status' => 'succeeded', 'latest_charge' => 'ch_'.$suffix, 'metadata' => $metadata], $changes);
        $charge = ['object' => 'charge', 'id' => 'ch_'.$suffix, 'payment_intent' => 'pi_'.$suffix, 'amount' => $intent['amount_received'],
            'currency' => $intent['currency'], 'livemode' => $intent['livemode'], 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'created' => now()->timestamp];
        Http::fake(function ($request) use ($session, $intent, $charge) {
            $this->assertSame(0, DB::transactionLevel());
            if (str_ends_with($request->url(), '/account')) {
                return Http::response(['id' => 'acct_fixture', 'object' => 'account']);
            }
            if (str_contains($request->url(), '/checkout/sessions/')) {
                return Http::response($session);
            }
            if (str_contains($request->url(), '/payment_intents/')) {
                return Http::response($intent);
            }
            if (str_contains($request->url(), '/charges/')) {
                return Http::response($charge);
            }
            throw new \RuntimeException('Unexpected fixture request');
        });
        $payload = ['id' => 'evt_'.$suffix, 'object' => 'event', 'type' => 'checkout.session.completed', 'created' => now()->timestamp, 'livemode' => false,
            'api_version' => '2026-08-26.dahlia', 'data' => ['object' => $session]];

        return StripeEvent::factory()->create(['stripe_event_id' => $payload['id'], 'type' => $payload['type'], 'payload' => $payload]);
    }

    private function apply(StripeEvent $event): void
    {
        $result = app(PaymentResultResolver::class)->resolve($event);
        $attempt = PaymentAttempt::query()->where('reference', $result->attemptReference)->firstOrFail();
        app(PaymentResultApplier::class)->apply($attempt, $result, $event);
    }

    #[DataProvider('mismatchedEvidence')]
    public function test_incompatible_evidence_opens_incident_without_purchase(array $changes): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $this->apply($this->fixture($attempt, 'fixture', $changes));
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        $this->assertGreaterThan(0, FinancialIncident::query()->count());
        $this->assertDatabaseCount('order_notifications', 0);
    }

    public static function mismatchedEvidence(): array
    {
        return [[['livemode' => true]], [['currency' => 'usd']], [['metadata' => ['attempt_reference' => 'foreign', 'order_reference' => 'foreign']]]];
    }

    public function test_signed_webhook_processes_pending_event_with_real_resolver(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt);
        $body = json_encode($event->payload, JSON_THROW_ON_ERROR);
        $event->raw_body = $body;
        $event->body_hash = hash('sha256', $body);
        $event->save();
        $signature = 't='.now()->timestamp.',v1='.hash_hmac('sha256', now()->timestamp.'.'.$body, 'whsec_fixture');
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
        $this->assertSame('processed', $event->fresh()->status);
    }

    public function test_refunds_failed_then_successful_do_not_change_original_money(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt);
        $this->apply($event);
        $result = app(PaymentResultResolver::class)->resolve($event);
        $data = get_object_vars($result);
        $data['refunds'] = [['id' => 're_fixture', 'amount' => 150800, 'status' => 'failed', 'created' => now()->timestamp]];
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult(...$data));
        $this->assertNull(Refund::query()->sole()->confirmed_at);
        $data['refunds'][0]['status'] = 'succeeded';
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult(...$data));
        $this->assertNotNull(Refund::query()->sole()->confirmed_at);
        $data['refunds'][0]['status'] = 'pending';
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult(...$data));
        $this->assertSame('succeeded', Refund::query()->sole()->status);
        $this->assertSame(150800, Payment::query()->sole()->amount_cents);
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
    }

    public function test_early_event_records_payment_order_outbox_and_processed_event_atomically(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt);
        $this->apply($event);
        $this->apply($event);
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::Paid, $attempt->fresh()->status);
        $this->assertSame('cs_test_fixture', $attempt->fresh()->stripe_session_id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertSame('processed', $event->fresh()->status);
        Mail::assertSent(PurchaseConfirmed::class, 1);
    }

    public function test_wrong_amount_records_money_and_incident_without_fulfilling_purchase(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt, 'fixture', ['amount_received' => 150801]);
        $this->apply($event);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        $this->assertSame(150801, Payment::query()->sole()->amount_cents);
        $this->assertDatabaseCount('financial_incidents', 1);
        $this->assertDatabaseCount('order_notifications', 0);
    }

    public function test_second_distinct_charge_is_preserved_without_second_purchase_effect(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $this->apply($this->fixture($attempt));
        Http::swap(new Factory);
        $second = PaymentAttempt::factory()->create(['order_id' => $attempt->order_id]);
        $this->apply($this->fixture($second, 'second'));
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('financial_incidents', 1);
        $this->assertDatabaseCount('order_notifications', 1);
    }

    public function test_outbox_failure_rolls_back_money_and_preserves_pending_event(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt);
        OrderNotification::creating(fn () => false);
        try {
            $this->apply($event);
            $this->fail('Expected persistence failure');
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        $this->assertSame('pending', $event->fresh()->status);
    }

    public function test_paid_does_not_regress_on_expired_evidence(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt);
        $this->apply($event);
        Http::swap(new Factory);
        $session = $event->payload['data']['object'];
        $session['status'] = 'expired';
        $session['payment_status'] = 'unpaid';
        $session['payment_intent'] = null;
        Http::fake(['https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_fixture']), '*' => Http::response($session)]);
        $late = StripeEvent::factory()->create(['type' => 'checkout.session.expired', 'payload' => array_replace($event->payload, ['type' => 'checkout.session.expired', 'data' => ['object' => $session]])]);
        $this->apply($late);
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::Paid, $attempt->fresh()->status);
        $this->assertDatabaseHas('financial_incidents', ['order_id' => $attempt->order_id, 'kind' => 'expiration_after_payment']);
    }

    public function test_expiring_one_attempt_cannot_close_order_with_another_open_attempt(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        PaymentAttempt::factory()->create(['order_id' => $attempt->order_id, 'status' => PaymentAttemptStatus::Open]);
        $result = new VerifiedPaymentResult($attempt->reference, $attempt->order->reference, 'stripe_api', 'evt_expired', 'acct_fixture', false,
            'MXN', 150800, 'expired', 'cs_test_fixture');
        app(PaymentResultApplier::class)->apply($attempt, $result);
        $this->assertSame(PaymentAttemptStatus::Expired, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
    }

    public function test_completed_unpaid_is_not_payment(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $event = $this->fixture($attempt);
        Http::swap(new Factory);
        $session = $event->payload['data']['object'];
        $session['payment_status'] = 'unpaid';
        $session['payment_intent'] = null;
        Http::fake(['https://api.stripe.com/v1/account' => Http::response(['id' => 'acct_fixture']), '*' => Http::response($session)]);
        $this->apply($event);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('order_notifications', 0);
    }
}
