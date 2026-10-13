<?php

namespace Tests\Feature;

use App\Data\VerifiedPaymentResult;
use App\Enums\OrderStatus;
use App\Models\FinancialIncident;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\PaymentReconciler;
use App\Services\PaymentResultApplier;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundAndDisputeTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Mail::fake();
        config(['store.recovery.cache_store' => 'array']);
    }

    private function paidAttempt(): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create(['stripe_session_id' => 'cs_test_fixture']);
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult($attempt->reference, $attempt->order->reference,
            'fixture', 'evt_paid', 'acct_fixture', false, 'MXN', 150800, 'paid', 'cs_test_fixture', 'pi_fixture', 'ch_fixture', now()->timestamp));

        return $attempt->refresh();
    }

    /** @param array<string, mixed> $object */
    private function signedEvent(PaymentAttempt $attempt, string $type, array $object, string $id): void
    {
        $metadata = ['attempt_reference' => $attempt->reference, 'order_reference' => $attempt->order->reference];
        $charge = ['id' => 'ch_fixture', 'object' => 'charge', 'payment_intent' => 'pi_fixture', 'amount' => 150800,
            'currency' => 'mxn', 'livemode' => false, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'created' => now()->timestamp];
        $intent = ['id' => 'pi_fixture', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 150800,
            'amount_received' => 150800, 'currency' => 'mxn', 'livemode' => false, 'metadata' => $metadata, 'latest_charge' => 'ch_fixture'];
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($object, $charge, $intent) {
            $this->assertSame(0, DB::transactionLevel());

            return Http::response(match (true) {
                str_ends_with($request->url(), '/account') => ['id' => 'acct_fixture'],
                str_contains($request->url(), '/refunds/'), str_contains($request->url(), '/disputes/') => $object,
                str_contains($request->url(), '/charges/') => $charge,
                str_contains($request->url(), '/payment_intents/') => $intent,
                default => throw new \RuntimeException('Unexpected remote fixture request'),
            });
        });
        $payload = ['id' => $id, 'object' => 'event', 'type' => $type, 'created' => now()->timestamp, 'livemode' => false,
            'api_version' => config('services.stripe.api_version'), 'data' => ['object' => $object]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_fixture');
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
    }

    #[DataProvider('refundStates')]
    public function test_received_refund_event_preserves_original_and_net_counts_only_confirmed(string $state, int $amount, int $net): void
    {
        $attempt = $this->paidAttempt();
        $original = $attempt->order->getRawOriginal();
        $this->signedEvent($attempt, 'refund.updated', ['object' => 'refund', 'id' => 're_fixture', 'charge' => 'ch_fixture',
            'amount' => $amount, 'currency' => 'mxn', 'status' => $state, 'created' => now()->timestamp], 'evt_refund');
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseHas('refunds', ['stripe_refund_id' => 're_fixture', 'status' => $state, 'amount_cents' => $amount]);
        $this->assertSame($original, $attempt->order->fresh()->getRawOriginal());
        $this->assertSame(150800, Payment::query()->sole()->amount_cents);
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/orders/'.$attempt->order->reference)
            ->assertOk()->assertSeeInOrder(['Neto', Money::format($net)]);
    }

    public static function refundStates(): array
    {
        return [['succeeded', 10000, 140800], ['succeeded', 150800, 0], ['pending', 10000, 150800], ['failed', 10000, 150800], ['canceled', 10000, 150800]];
    }

    public function test_old_pending_event_cannot_degrade_confirmed_refund(): void
    {
        $attempt = $this->paidAttempt();
        $refund = ['object' => 'refund', 'id' => 're_fixture', 'charge' => 'ch_fixture', 'amount' => 150800,
            'currency' => 'mxn', 'status' => 'succeeded', 'created' => now()->timestamp];
        $this->signedEvent($attempt, 'refund.updated', $refund, 'evt_new');
        $this->signedEvent($attempt, 'refund.created', array_replace($refund, ['status' => 'pending']), 'evt_old');
        $this->assertSame('succeeded', Refund::query()->sole()->status);
        $this->assertSame('fully_refunded', app(NotificationDispatcher::class)->pickupBlockReason($attempt->order->fresh()));
    }

    public function test_dispute_event_is_deduplicated_and_blocks_pickup_until_won(): void
    {
        $attempt = $this->paidAttempt();
        $dispute = ['id' => 'dp_fixture', 'object' => 'dispute', 'charge' => 'ch_fixture', 'status' => 'needs_response'];
        $this->signedEvent($attempt, 'charge.dispute.created', $dispute, 'evt_dispute');
        $this->assertDatabaseCount('financial_incidents', 1);
        $this->assertSame('financial_incident', app(NotificationDispatcher::class)->pickupBlockReason($attempt->order->fresh()));
        $this->signedEvent($attempt, 'charge.dispute.closed', array_replace($dispute, ['status' => 'won']), 'evt_won');
        $this->assertNotNull(FinancialIncident::query()->sole()->resolved_at);
        $this->assertNull(app(NotificationDispatcher::class)->pickupBlockReason($attempt->order->fresh()));
    }

    public function test_missing_refund_webhook_does_not_trigger_sweep_of_paid_charges(): void
    {
        $attempt = $this->paidAttempt();
        $this->assertSame(0, app(PaymentReconciler::class)->run(25));
        Http::assertNothingSent();
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
    }
}
