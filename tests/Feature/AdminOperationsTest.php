<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\AuditEntry;
use App\Models\FinancialIncident;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\StoreSetting;
use App\Models\StripeEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config(['store.recovery.cache_store' => 'array']);
    }

    public function test_order_diagnostics_show_associated_pending_events_and_cancellation_reason_without_raw_payload(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $attempt = PaymentAttempt::factory()->create(['order_id' => $order->id, 'stripe_session_id' => 'cs_test_linked']);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'payment_attempt_id' => $attempt->id, 'stripe_charge_id' => 'ch_linked']);
        StripeEvent::factory()->create(['stripe_event_id' => 'evt_order_pending', 'type' => 'checkout.session.completed', 'status' => 'pending',
            'payload' => ['data' => ['object' => ['metadata' => ['order_reference' => $order->reference], 'secret' => 'secret-payload-redacted']]]]);
        StripeEvent::factory()->create(['stripe_event_id' => 'evt_refund_pending', 'type' => 'refund.updated', 'status' => 'pending',
            'payload' => ['data' => ['object' => ['id' => 're_linked', 'charge' => $payment->stripe_charge_id]]]]);
        StripeEvent::factory()->create(['stripe_event_id' => 'evt_unrelated_pending', 'status' => 'pending']);
        OrderNotification::factory()->create(['order_id' => $order->id, 'status' => NotificationStatus::Cancelled, 'cancellation_reason' => 'fully_refunded']);
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/orders/'.$order->reference)->assertOk()
            ->assertSee('evt_order_pending')->assertSee('evt_refund_pending')->assertSee('fully_refunded')
            ->assertDontSee('evt_unrelated_pending')->assertDontSee('secret-payload-redacted');
    }

    public function test_diagnostics_mask_destinations_and_payloads_and_report_stale_cron(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderNotification::factory()->create(['order_id' => $order->id, 'destination' => 'private-recipient@example.test', 'status' => NotificationStatus::Uncertain,
            'last_error' => 'private-recipient@example.test secret remote text']);
        StripeEvent::factory()->create(['payload' => ['secret' => 'payload-must-stay-private'], 'status' => 'pending']);
        Cache::store('array')->forever('store:operations:payments', ['last_success_at' => now()->subHour()->toIso8601String(), 'errors' => 2]);
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/notifications')->assertOk()
            ->assertSee('Retrasado')->assertSee('Por confirmar')->assertSee('p***@***')->assertSeeInOrder(['Avisos de Stripe pendientes de procesar', '1'])
            ->assertDontSee('private-recipient@example.test')->assertDontSee('payload-must-stay-private')->assertDontSee('secret remote text');
        $this->actingAs(User::factory()->installer()->create())->get('/admin/notifications')->assertForbidden();
    }

    public function test_failed_remote_recovery_is_audited_and_does_not_release_unknown(): void
    {
        $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Unknown, 'stripe_session_id' => 'cs_test_pending']);
        Http::fake(['*' => Http::response([], 503)]);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/admin/payments/'.$attempt->id.'/recover')->assertRedirect('/admin/orders/'.$attempt->order->reference)->assertSessionHasErrors('payment');
        $this->assertSame(PaymentAttemptStatus::Unknown, $attempt->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        $this->assertDatabaseHas('audit_entries', ['action' => 'payment_recovery.requested', 'actor_id' => $admin->id, 'entity_id' => $attempt->id]);
        $this->actingAs(User::factory()->installer()->create())->post('/admin/payments/'.$attempt->id.'/recover')->assertForbidden();
    }

    public function test_paid_attempt_cannot_be_swept_by_administrative_recovery(): void
    {
        $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Paid]);
        $this->actingAs(User::factory()->administrator()->create())->postJson('/admin/payments/'.$attempt->id.'/recover')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_uncertain_notifications_require_explicit_resend_and_are_not_retried_blindly(): void
    {
        $notice = OrderNotification::factory()->create(['status' => NotificationStatus::Uncertain]);
        $this->actingAs(User::factory()->administrator()->create())->postJson('/admin/notifications/'.$notice->id.'/retry')->assertUnprocessable();
        $this->assertSame(NotificationStatus::Uncertain, $notice->fresh()->status);
    }

    public function test_authorized_failed_notice_retry_is_audited_and_records_one_acceptance(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        $notice = OrderNotification::factory()->create(['order_id' => $order->id, 'type' => 'purchase', 'status' => NotificationStatus::Failed,
            'next_attempt_at' => now()->subMinute()]);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/admin/notifications/'.$notice->id.'/retry')->assertRedirect('/admin/notifications');
        $this->assertSame(NotificationStatus::Accepted, $notice->fresh()->status);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertDatabaseHas('audit_entries', ['action' => 'notification_retry.requested', 'entity_id' => $notice->id, 'actor_id' => $admin->id]);
        $this->postJson('/admin/notifications/'.$notice->id.'/retry')->assertUnprocessable();
        $this->assertDatabaseCount('notification_attempts', 1);
    }

    public function test_all_is_default_and_paid_filter_includes_refunds_and_incidents_and_filters_preserve_history(): void
    {
        $paid = Order::factory()->create(['status' => OrderStatus::Paid]);
        $refund = Order::factory()->create(['status' => OrderStatus::Paid]);
        Refund::factory()->create(['payment_id' => Payment::factory()->create(['order_id' => $refund->id])->id]);
        FinancialIncident::factory()->create(['order_id' => $paid->id]);
        $pending = Order::factory()->create();
        $closed = Order::factory()->create(['status' => OrderStatus::ClosedUnpaid]);
        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin/orders')->assertOk()->assertSee($paid->reference)->assertSee($refund->reference)
            ->assertSee($pending->reference)->assertSee($closed->reference);
        $this->get('/admin/orders?status=paid')->assertOk()->assertSee($paid->reference)->assertSee($refund->reference)
            ->assertDontSee($pending->reference)->assertDontSee($closed->reference);
        foreach (['pending' => $pending, 'closed_unpaid' => $closed] as $status => $order) {
            $this->get('/admin/orders?status='.$status)->assertOk()->assertSee($order->reference)->assertDontSee($paid->reference);
        }
        $this->get('/admin/orders?status=all')->assertOk()->assertSee($paid->reference)->assertSee($pending->reference)->assertSee($closed->reference);
        $this->getJson('/admin/orders?status=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('orders', 4);
    }

    public function test_detail_preserves_originals_and_shows_all_charges_and_confirmed_net(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);
        OrderLine::factory()->create(['order_id' => $order->id, 'name' => 'Equipo original']);
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $second = Payment::factory()->create(['order_id' => $order->id]);
        Refund::factory()->create(['payment_id' => $payment->id, 'amount_cents' => 10000, 'status' => 'succeeded']);
        Refund::factory()->create(['payment_id' => $payment->id, 'amount_cents' => 25000, 'status' => 'failed']);
        $this->actingAs(User::factory()->administrator()->create())->get('/admin/orders/'.$order->reference)
            ->assertOk()->assertSee($order->contact_email)->assertSee('Equipo original')
            ->assertSee($payment->stripe_charge_id)->assertSee($second->stripe_charge_id)->assertSee('$2,916.00')
            ->assertSee('$1,508.00')->assertSee('Stripe')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(150800, $order->fresh()->total_cents);
    }

    public function test_admin_operations_require_completed_mfa_and_active_administrator(): void
    {
        $order = Order::factory()->create();
        foreach (['/admin/orders', '/admin/orders/'.$order->reference, '/admin/settings'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->actingAs(User::factory()->installer()->create());
        $this->get('/admin/orders')->assertForbidden();
        $this->put('/admin/settings', ['pickup_name' => 'Forbidden'])->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->withSession(['admin.verified' => null]);
        $this->get('/admin/orders')->assertRedirect('/segundo-factor');
        $this->put('/admin/settings', [])->assertRedirect('/segundo-factor');
    }

    public function test_settings_are_versioned_audited_and_public_text_is_escaped(): void
    {
        $setting = StoreSetting::current();
        $admin = User::factory()->administrator()->create();
        $data = ['pickup_address' => 'Dirección nueva', 'pickup_hours' => '9 a 18',
            'contact_email' => 'store@example.test', 'contact_phone' => '+526141234567',
            'terms' => '<script>alert(1)</script>Condiciones', 'privacy' => 'Privacidad real', 'returns' => 'Devoluciones reales',
            'retention_policy' => 'Conservar evidencia financiera; revisión de contactos a los 24 meses.'];
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->put('/admin/settings', $data + ['version' => 999, 'public_markup_basis_points' => 9999, 'stripe_secret' => 'ignored'])
            ->assertRedirect('/admin/settings')->assertSessionHasNoErrors();
        $fresh = $setting->fresh();
        $this->assertSame(2, $fresh->version);
        $this->assertSame(3000, $fresh->public_markup_basis_points);
        $this->assertSame($data['retention_policy'], $fresh->retention_policy);
        $audit = AuditEntry::where('action', 'store_settings.updated')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame(['changed' => true], $audit->changes['terms']);
        $this->assertStringNotContainsString($data['contact_email'], json_encode($audit->changes));
        $this->get('/informacion')->assertOk()->assertSee($data['terms'])->assertDontSee($data['terms'], false)->assertSee('Privacidad real');
        $this->put('/admin/settings', $data + ['id' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $fresh->fresh()->version);
        $this->putJson('/admin/settings', array_replace($data, ['contact_email' => 'invalid']))->assertUnprocessable();
    }
}
