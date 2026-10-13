<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Mail\PurchaseConfirmed;
use App\Mail\ReadyForPickup;
use App\Models\FinancialIncident;
use App\Models\NotificationAttempt;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_refund_does_not_prevent_recording_abandoned_sending_claim_as_uncertain(): void
    {
        $notification = $this->pickup();
        $notification->forceFill(['status' => NotificationStatus::Claimed, 'claim_token' => '00000000-0000-4000-8000-000000000001', 'lease_expires_at' => now()->subMinute()])->save();
        NotificationAttempt::factory()->create(['order_notification_id' => $notification->id, 'claim_token' => $notification->claim_token, 'status' => 'sending']);
        Refund::factory()->create(['payment_id' => Payment::query()->sole()->id]);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Uncertain, $notification->fresh()->status);
        $this->assertSame('uncertain', NotificationAttempt::query()->sole()->status);
        Mail::assertNothingSent();
    }

    public function test_refund_after_abandoned_prepared_claim_cancels_without_send(): void
    {
        $notification = $this->pickup();
        $notification->forceFill(['status' => NotificationStatus::Claimed, 'claim_token' => '00000000-0000-4000-8000-000000000001', 'lease_expires_at' => now()->subMinute()])->save();
        NotificationAttempt::factory()->create(['order_notification_id' => $notification->id, 'claim_token' => $notification->claim_token, 'status' => 'prepared']);
        Refund::factory()->create(['payment_id' => Payment::query()->sole()->id]);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Cancelled, $notification->fresh()->status);
        $this->assertSame('failed', NotificationAttempt::query()->sole()->status);
        Mail::assertNothingSent();
    }

    public function test_insufficient_command_budget_keeps_outbox_pending_without_claim_or_smtp(): void
    {
        $notification = $this->notification();
        config(['services.stripe.command_deadline' => hrtime(true) / 1e9 + 1]);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Pending, $notification->fresh()->status);
        $this->assertDatabaseCount('notification_attempts', 0);
        Mail::assertNothingSent();
    }

    public function test_local_refund_after_preparation_prevents_pickup_smtp(): void
    {
        $notification = $this->pickup();
        NotificationAttempt::created(function (): void {
            Refund::factory()->create(['payment_id' => Payment::query()->sole()->id]);
        });
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Cancelled, $notification->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_mail_cancelled_by_listener_is_not_provider_acceptance(): void
    {
        $notification = $this->notification();
        $manager = new MailManager($this->app);
        Mail::swap($manager);
        Event::listen(MessageSending::class, fn () => false);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Failed, $notification->fresh()->status);
        $this->assertNull($notification->fresh()->accepted_at);
    }

    private function pickup(): OrderNotification
    {
        $notification = $this->notification();
        $notification->forceFill(['type' => 'pickup', 'notification_key' => 'pickup:'.$notification->order->reference])->save();
        Payment::factory()->create(['order_id' => $notification->order_id]);

        return $notification;
    }

    #[DataProvider('retryableStatuses')]
    public function test_pickup_total_refund_cancels_pending_and_failed_without_smtp(NotificationStatus $initialStatus): void
    {
        $notification = $this->pickup();
        $notification->status = $initialStatus;
        $notification->save();
        $payment = Payment::query()->sole();
        Refund::factory()->create(['payment_id' => $payment->id]);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Cancelled, $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->cancelled_at);
        $this->assertSame('fully_refunded', $notification->fresh()->cancellation_reason);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('notification_attempts', 0);
    }

    public static function retryableStatuses(): array
    {
        return [[NotificationStatus::Pending], [NotificationStatus::Failed]];
    }

    public function test_manual_pickup_and_resend_are_blocked_by_current_incident(): void
    {
        $notification = $this->pickup();
        app(NotificationDispatcher::class)->attempt($notification);
        FinancialIncident::factory()->create(['order_id' => $notification->order_id]);
        $this->actingAs(User::factory()->administrator()->create());
        $url = '/admin/orders/'.$notification->order->reference.'/pickup-notification';
        $this->postJson($url)->assertUnprocessable();
        $this->postJson($url.'/resend', ['confirm_resend' => '1', 'confirm_possible_duplicate' => '1', 'intention_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('audit_entries', 0);
        Mail::assertSent(ReadyForPickup::class, 1);
    }

    public function test_incident_blocks_pickup_until_resolved_and_eligibility_is_revalidated(): void
    {
        $notification = $this->pickup();
        $incident = FinancialIncident::factory()->create(['order_id' => $notification->order_id]);
        app(NotificationDispatcher::class)->attempt($notification);
        Mail::assertNothingSent();
        $this->assertSame('financial_incident', $notification->fresh()->blocked_reason);
        $incident->resolved_at = now();
        $incident->save();
        app(NotificationDispatcher::class)->attempt($notification);
        Mail::assertSent(ReadyForPickup::class, 1);
        $this->assertSame(NotificationStatus::Accepted, $notification->fresh()->status);
    }

    public function test_accepted_pickup_history_is_preserved_after_refund(): void
    {
        $notification = $this->pickup();
        app(NotificationDispatcher::class)->attempt($notification);
        Refund::factory()->create(['payment_id' => Payment::query()->sole()->id]);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Accepted, $notification->fresh()->status);
        $this->assertDatabaseCount('notification_attempts', 1);
    }

    public function test_admin_double_click_pickup_is_deduplicated_and_installer_is_denied(): void
    {
        $this->withoutVite();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        Payment::factory()->create(['order_id' => $order->id]);
        $admin = User::factory()->administrator()->create();
        $url = '/admin/orders/'.$order->reference.'/pickup-notification';
        $this->actingAs(User::factory()->installer()->create())->post($url)->assertForbidden();
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();
        $this->assertDatabaseCount('order_notifications', 1);
        Mail::assertSent(ReadyForPickup::class, 1);
        $this->get('/admin')->assertOk()->assertSee('Avisar para recoger');
    }

    public function test_manual_resend_requires_confirmation_and_keeps_audited_intention(): void
    {
        $notification = $this->pickup();
        app(NotificationDispatcher::class)->attempt($notification);
        $this->actingAs(User::factory()->administrator()->create());
        $url = '/admin/orders/'.$notification->order->reference.'/pickup-notification/resend';
        $this->postJson($url, [])->assertUnprocessable();
        $input = ['confirm_resend' => '1', 'intention_key' => (string) Str::uuid(), 'confirm_possible_duplicate' => '1'];
        $this->post($url, $input)->assertRedirect();
        $this->post($url, $input)->assertRedirect();
        $this->assertDatabaseCount('order_notifications', 2);
        $this->assertDatabaseCount('audit_entries', 1);
        Mail::assertSent(ReadyForPickup::class, 2);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Mail::fake();
    }

    private function notification(): OrderNotification
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        return OrderNotification::factory()->create(['order_id' => $order->id, 'notification_key' => 'purchase:'.$order->reference]);
    }

    private function rejectingTransport(int $code): void
    {
        $manager = new MailManager($this->app);
        $transport = new class($code) extends AbstractTransport
        {
            public function __construct(private int $errorCode)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                if (DB::transactionLevel() !== 0) {
                    throw new \LogicException('SMTP inside transaction');
                }
                throw new TransportException('Fixture transport failure', $this->errorCode);
            }

            public function __toString(): string
            {
                return 'fixture';
            }
        };
        $manager->mailer('array')->setSymfonyTransport($transport);
        Mail::swap($manager);
    }

    public function test_claim_sends_once_outside_locks_and_records_provider_acceptance(): void
    {
        $notification = $this->notification();
        app(NotificationDispatcher::class)->attempt($notification);
        app(NotificationDispatcher::class)->attempt($notification);
        Mail::assertSent(PurchaseConfirmed::class, 1);
        $this->assertSame(NotificationStatus::Accepted, $notification->fresh()->status);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertNotNull($notification->fresh()->accepted_at);
        $this->assertSame('accepted', NotificationAttempt::query()->sole()->status);
    }

    public function test_attempt_deferred_until_outer_commit(): void
    {
        $notification = $this->notification();
        DB::beginTransaction();
        app(NotificationDispatcher::class)->attempt($notification);
        Mail::assertNothingSent();
        DB::commit();
        Mail::assertSent(PurchaseConfirmed::class, 1);
    }

    public function test_timeout_remains_uncertain_and_is_not_resent(): void
    {
        $notification = $this->notification();
        $this->rejectingTransport(0);
        app(NotificationDispatcher::class)->attempt($notification);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Uncertain, $notification->fresh()->status);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertSame(OrderStatus::Paid, $notification->order->fresh()->status);
    }

    public function test_definitive_smtp_rejection_is_retryable_without_losing_payment(): void
    {
        $notification = $this->notification();
        $this->rejectingTransport(550);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Failed, $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->next_attempt_at);
        $this->assertSame(OrderStatus::Paid, $notification->order->fresh()->status);
    }

    public function test_abandoned_sending_claim_is_uncertain_without_smtp(): void
    {
        $notification = $this->notification();
        $notification->forceFill(['status' => NotificationStatus::Claimed, 'claim_token' => '00000000-0000-4000-8000-000000000001', 'lease_expires_at' => now()->subMinute()])->save();
        NotificationAttempt::factory()->create(['order_notification_id' => $notification->id, 'claim_token' => $notification->claim_token, 'status' => 'sending']);
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame(NotificationStatus::Uncertain, $notification->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_unexpired_claim_is_exclusive(): void
    {
        $notification = $this->notification();
        $notification->forceFill(['status' => NotificationStatus::Claimed, 'lease_expires_at' => now()->addMinute()])->save();
        app(NotificationDispatcher::class)->attempt($notification);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('notification_attempts', 0);
    }

    public function test_purchase_template_projects_snapshot_and_pickup_information_without_cost(): void
    {
        $notification = $this->notification();
        $html = (new PurchaseConfirmed($notification->order))->render();
        $this->assertStringContainsString($notification->order->reference, $html);
        $this->assertStringContainsString('segundo correo', $html);
        $this->assertStringContainsString('$1,508.00', $html);
        $this->assertStringNotContainsString('base_cost', $html);
    }
}
