<?php

namespace Tests\Feature;

use App\Exceptions\UnknownStripeOutcome;
use App\Mail\InstallerInvitationMail;
use App\Mail\PasswordResetMail;
use App\Models\AuditEntry;
use App\Models\InstallerInvitation;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\InstallerInvitationService;
use App\Services\NotificationDispatcher;
use App\Services\OrderAccessService;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstallerAccessTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        StoreSetting::factory()->create();
        Mail::fake();
        Http::preventStrayRequests();
    }

    private function invite(User $installer): string
    {
        app(InstallerInvitationService::class)->invite($installer, User::factory()->administrator()->create());
        $token = null;
        Mail::assertSent(InstallerInvitationMail::class, function ($mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });
        $this->assertTrue(is_string($token));

        return $token;
    }

    public function test_admin_creates_inactive_installer_and_activation_chooses_password(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post(route('admin.installers.store'), ['name' => 'Técnico <prueba>', 'email' => 'instalador@example.test'])
            ->assertRedirect(route('admin.installers.index'));
        $installer = User::where('email', 'instalador@example.test')->sole();
        $this->assertFalse($installer->isActive());
        $token = null;
        Mail::assertSent(InstallerInvitationMail::class, function ($mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });
        $invitation = InstallerInvitation::query()->sole();
        $this->assertTrue(hash_equals(hash('sha256', $token), $invitation->token_hash));
        $this->assertNotNull($invitation->expires_at);
        $this->get(route('installer.activate', $token))->assertOk();
        $this->post(route('installer.activate.store'), ['token' => $token, 'password' => 'Testing-password-123', 'password_confirmation' => 'Testing-password-123'])->assertRedirect(route('login'));
        $this->assertTrue($installer->fresh()->isActive());
        $this->assertTrue(Hash::check('Testing-password-123', $installer->fresh()->password));
        $this->assertNotNull($invitation->fresh()->used_at);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertDatabaseHas('audit_entries', ['action' => 'installer.invited']);
        $this->assertStringNotContainsString('token_hash', json_encode($invitation));
        $this->assertStringNotContainsString('Testing-password-123', json_encode(DB::table('audit_entries')->get()));
        $this->assertSame('accepted', OrderNotification::query()->sole()->status->value);
    }

    public function test_resend_invalidates_previous_token_and_activation_is_one_use(): void
    {
        $installer = User::factory()->inactive()->create();
        $old = $this->invite($installer);
        Mail::fake();
        $new = $this->invite($installer);
        try {
            app(InstallerInvitationService::class)->activate($old, 'Testing-password-123');
            $this->fail('Previous token remained valid.');
        } catch (ValidationException) {
            $this->assertFalse($installer->fresh()->isActive());
        }
        $user = app(InstallerInvitationService::class)->activate($new, 'Testing-password-123');
        $this->assertTrue($user->isActive());
        $this->expectException(ValidationException::class);
        app(InstallerInvitationService::class)->activate($new, 'Testing-password-123');
    }

    public function test_expired_or_invalid_tokens_do_not_activate_an_installer(): void
    {
        $installer = User::factory()->inactive()->create();
        $token = $this->invite($installer);
        $this->travel(49)->hours();
        try {
            app(InstallerInvitationService::class)->activate($token, 'Testing-password-123');
            $this->fail('Expired token activated account.');
        } catch (ValidationException) {
            $this->assertFalse($installer->fresh()->isActive());
        }
        $this->get(route('installer.activate', str_repeat('x', 64)))->assertNotFound();
        $this->post(route('installer.activate.store'), ['token' => str_repeat('x', 64), 'password' => 'Testing-password-123', 'password_confirmation' => 'Testing-password-123'])->assertSessionHasErrors('token');
    }

    public function test_only_active_administrator_can_manage_invitations_and_there_is_no_registration(): void
    {
        $this->get(route('admin.installers.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->installer()->create())->get(route('admin.installers.index'))->assertForbidden();
        $this->post(route('admin.installers.store'), ['name' => 'Prueba', 'email' => 'blocked@example.test'])->assertForbidden();
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        Mail::assertNothingOutgoing();
    }

    public function test_failed_mail_is_recoverable_with_same_invitation_and_no_secret_in_plaintext(): void
    {
        $installer = User::factory()->inactive()->create();
        Mail::swap(app('mail.manager'));
        config(['mail.default' => 'missing-provider']);
        app(InstallerInvitationService::class)->invite($installer, User::factory()->administrator()->create());
        $notification = OrderNotification::query()->sole();
        $this->assertSame('failed', $notification->status->value);
        $token = $notification->payload['token'];
        $this->assertFalse(str_contains($notification->getRawOriginal('payload'), $token));
        $this->assertStringNotContainsString('payload', json_encode($notification));
        config(['mail.default' => 'array']);
        Mail::fake();
        $notification->next_attempt_at = null;
        $notification->save();
        app(NotificationDispatcher::class)->attempt($notification);
        $this->assertSame('accepted', $notification->fresh()->status->value);
        $this->assertDatabaseCount('installer_invitations', 1);
        $this->assertDatabaseCount('notification_attempts', 2);
        $this->assertNull($notification->fresh()->payload);
    }

    public function test_expired_access_notice_is_cancelled_before_recovery_and_erases_secret(): void
    {
        $installer = User::factory()->inactive()->create();
        Mail::swap(app('mail.manager'));
        config(['mail.default' => 'missing-provider']);
        app(InstallerInvitationService::class)->invite($installer, User::factory()->administrator()->create());
        $notice = OrderNotification::query()->sole();
        $this->assertSame('failed', $notice->status->value);
        InstallerInvitation::query()->update(['expires_at' => now()->subSecond()]);
        $notice->forceFill(['next_attempt_at' => null])->save();
        Mail::fake();
        app(NotificationDispatcher::class)->attempt($notice);
        $this->assertSame('cancelled', $notice->fresh()->status->value);
        $this->assertNull($notice->fresh()->payload);
        $this->assertDatabaseCount('notification_attempts', 1);
        Mail::assertNothingOutgoing();
    }

    public function test_invitation_audit_failure_rolls_back_token_and_outbox_without_sending(): void
    {
        $installer = User::factory()->inactive()->create();
        $admin = User::factory()->administrator()->create();
        AuditEntry::creating(fn () => false);
        try {
            app(InstallerInvitationService::class)->invite($installer, $admin);
            $this->fail('Invitation and notification must roll back with its audit.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('installer_invitations', 0);
            $this->assertDatabaseCount('order_notifications', 0);
            Mail::assertNothingOutgoing();
        } finally {
            AuditEntry::flushEventListeners();
        }
    }

    public function test_two_activated_installers_share_the_same_tier(): void
    {
        $one = User::factory()->inactive()->create();
        $two = User::factory()->inactive()->create();
        $first = app(InstallerInvitationService::class)->activate($this->invite($one), 'Testing-password-123');
        Mail::fake();
        $second = app(InstallerInvitationService::class)->activate($this->invite($two), 'Testing-password-123');
        $this->assertSame($first->role, $second->role);
        $this->assertTrue($first->isActive() && $second->isActive());
        $this->post('/login', ['email' => $first->email, 'password' => 'Testing-password-123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($first);
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        foreach ([$first, $second] as $installer) {
            $this->actingAs($installer)->getJson(route('store.product', $product->slug))
                ->assertOk()->assertJsonPath('data.price.total_cents', 127600);
        }
    }

    public function test_password_recovery_does_not_enumerate_accounts_and_reuses_native_broker(): void
    {
        $user = User::factory()->installer()->create(['email' => 'reset@example.test']);
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('status');
        $first = session('status');
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('status', $first);
        $token = null;
        Mail::assertSent(PasswordResetMail::class, function ($mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token,
            'password' => 'New-testing-password-123', 'password_confirmation' => 'New-testing-password-123'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('New-testing-password-123', $user->fresh()->password));
        $this->assertSame(1, $user->fresh()->auth_version);
        $this->assertNull($user->fresh()->remember_token);
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token,
            'password' => 'Again-testing-password-123', 'password_confirmation' => 'Again-testing-password-123'])->assertSessionHasErrors('email');
    }

    public function test_suspended_or_unactivated_accounts_cannot_recover_password(): void
    {
        foreach ([User::factory()->inactive()->create(), User::factory()->installer()->suspended()->create()] as $user) {
            $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
        }
        Mail::assertNothingOutgoing();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_suspension_revokes_old_sessions_remember_and_order_tokens_without_changing_snapshot(): void
    {
        $installer = User::factory()->installer()->create();
        $admin = User::factory()->administrator()->create();
        $order = Order::factory()->create(['user_id' => $installer->id, 'tier' => 'installer']);
        $token = app(OrderAccessService::class)->issue($order);
        $snapshot = $order->fresh()->getRawOriginal();
        DB::table('sessions')->insert(['id' => 'fixture-old-session', 'user_id' => $installer->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($installer);
        app(InstallerInvitationService::class)->suspend($installer, $admin);
        $this->assertFalse($installer->fresh()->isActive());
        $this->assertNull($installer->fresh()->remember_token);
        $this->assertSame(1, $installer->fresh()->auth_version);
        $this->assertDatabaseCount('sessions', 0);
        $this->post('/checkout', [])->assertForbidden();
        $this->assertFalse(app(OrderAccessService::class)->allows($order, $installer, $token));
        $this->assertFalse(app(OrderAccessService::class)->allows($order, null, $token));
        foreach (['subtotal_cents', 'discount_cents', 'tax_cents', 'total_cents', 'contact_name', 'contact_email', 'phone_canonical'] as $field) {
            $this->assertSame($snapshot[$field], $order->fresh()->getRawOriginal($field));
        }
        $this->assertDatabaseHas('audit_entries', ['action' => 'installer.suspended']);
    }

    public function test_admin_can_reactivate_a_suspended_installer(): void
    {
        $admin = User::factory()->administrator()->create();
        $installer = User::factory()->installer()->create();
        $this->actingAs($admin)->post(route('admin.installers.suspend', $installer))->assertRedirect(route('admin.installers.index'));
        $this->assertNotNull($installer->fresh()->suspended_at);
        $this->get(route('admin.installers.index'))->assertSee('Activar acceso');
        $this->post(route('admin.installers.reactivate', $installer))->assertRedirect(route('admin.installers.index'));
        $this->assertNull($installer->fresh()->suspended_at);
        $this->assertTrue($installer->fresh()->isActive());
        $this->assertSame(2, $installer->fresh()->auth_version);
        $this->assertDatabaseHas('audit_entries', ['action' => 'installer.reactivated']);
    }

    public function test_reactivating_never_activated_installer_returns_to_pending_invitation(): void
    {
        $admin = User::factory()->administrator()->create();
        $installer = User::factory()->installer()->inactive()->suspended()->create();
        $this->actingAs($admin)->post(route('admin.installers.reactivate', $installer))->assertRedirect();
        $this->assertNull($installer->fresh()->suspended_at);
        $this->assertFalse($installer->fresh()->isActive());
    }

    public function test_admin_can_delete_installer_without_orders_but_not_with_orders(): void
    {
        $admin = User::factory()->administrator()->create();
        $free = User::factory()->installer()->create();
        $withOrder = User::factory()->installer()->create();
        Order::factory()->create(['user_id' => $withOrder->id, 'tier' => 'installer']);
        $this->actingAs($admin)->delete(route('admin.installers.destroy', $free))->assertRedirect(route('admin.installers.index'));
        $this->assertDatabaseMissing('users', ['id' => $free->id]);
        $this->delete(route('admin.installers.destroy', $withOrder))->assertSessionHasErrors('installer');
        $this->assertDatabaseHas('users', ['id' => $withOrder->id]);
    }

    public function test_deleting_a_freshly_invited_installer_removes_invitation_and_notifications(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post(route('admin.installers.store'), ['name' => 'Técnico', 'email' => 'nuevo@example.test'])->assertRedirect();
        $installer = User::where('email', 'nuevo@example.test')->firstOrFail();
        $this->delete(route('admin.installers.destroy', $installer))->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.test']);
        $this->assertDatabaseCount('installer_invitations', 0);
    }

    public function test_installers_cannot_reactivate_or_delete(): void
    {
        $installer = User::factory()->installer()->suspended()->create();
        $this->actingAs(User::factory()->installer()->create())->post(route('admin.installers.reactivate', $installer))->assertForbidden();
        $this->delete(route('admin.installers.destroy', $installer))->assertForbidden();
    }

    public function test_password_change_denies_order_access_from_old_authenticated_session(): void
    {
        $installer = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $installer->id]);
        $access = app(OrderAccessService::class);
        $token = $access->issue($order);
        $this->actingAs($installer);
        User::whereKey($installer->id)->update(['auth_version' => 1]);
        $this->assertFalse($access->allows($order, $installer, $token));
        $this->getJson(route('orders.show', $order->reference))->assertNotFound();
    }

    public function test_recovery_cannot_create_new_checkout_for_suspended_owner(): void
    {
        config(['services.stripe.enabled' => true, 'services.stripe.secret' => 'sk_test_fixture', 'services.stripe.account_id' => 'acct_fixture']);
        $installer = User::factory()->installer()->suspended()->create();
        $attempt = PaymentAttempt::factory()->create(['order_id' => Order::factory()->create(['user_id' => $installer->id])->id]);
        Http::fake(['*' => Http::response(['data' => [], 'has_more' => false])]);
        try {
            app(StripeGateway::class)->createCheckout($attempt);
            $this->fail('A suspended account must not create a new Checkout.');
        } catch (UnknownStripeOutcome) {
            Http::assertNotSent(fn ($request) => $request->method() === 'POST');
            $this->assertNull($attempt->fresh()->first_sent_at);
            $this->assertNull($attempt->fresh()->checkout_parameters);
        }
    }

    private function suspensionFixture(string $state): array
    {
        config(['services.stripe.enabled' => true, 'services.stripe.secret' => 'sk_test_fixture', 'services.stripe.account_id' => 'acct_fixture']);
        $installer = User::factory()->installer()->create();
        $attempt = PaymentAttempt::factory()->create(['order_id' => Order::factory()->create(['user_id' => $installer->id, 'tier' => 'installer'])->id,
            'stripe_session_id' => 'cs_test_suspension', 'status' => 'open']);
        $metadata = ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference];
        $session = ['id' => 'cs_test_suspension', 'object' => 'checkout.session', 'mode' => 'payment', 'livemode' => false,
            'amount_total' => $attempt->amount_cents, 'currency' => 'mxn', 'expires_at' => now()->addMinutes(30)->timestamp,
            'status' => $state === 'paid' ? 'complete' : 'expired', 'payment_status' => $state === 'paid' ? 'paid' : 'unpaid',
            'payment_intent' => $state === 'paid' ? 'pi_suspension' : null, 'url' => null, 'metadata' => $metadata];
        Http::fake(function ($request) use ($installer, $attempt, $session, $metadata, $state) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse($installer->fresh()->isActive());
            if ($state === 'unknown') {
                throw new ConnectionException('Simulated unavailable Stripe.');
            }
            if ($request->method() === 'POST' && $state === 'paid') {
                return Http::response(['error' => ['type' => 'invalid_request_error']], 400);
            }

            return match (true) {
                str_ends_with($request->url(), '/account') => Http::response(['id' => 'acct_fixture']),
                str_contains($request->url(), '/payment_intents/') => Http::response(['id' => 'pi_suspension', 'object' => 'payment_intent',
                    'status' => 'succeeded', 'amount' => $attempt->amount_cents, 'amount_received' => $attempt->amount_cents,
                    'currency' => 'mxn', 'livemode' => false, 'metadata' => $metadata, 'latest_charge' => 'ch_suspension']),
                str_contains($request->url(), '/charges/') => Http::response(['id' => 'ch_suspension', 'object' => 'charge', 'payment_intent' => 'pi_suspension',
                    'amount' => $attempt->amount_cents, 'currency' => 'mxn', 'livemode' => false, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'created' => now()->timestamp]),
                default => Http::response($session),
            };
        });

        return [$installer, $attempt];
    }

    public function test_suspension_expires_remotely_without_locks_and_applies_common_result(): void
    {
        [$installer, $attempt] = $this->suspensionFixture('expired');
        app(InstallerInvitationService::class)->suspend($installer, User::factory()->administrator()->create());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/expire'));
        $this->assertSame([], DB::table('financial_incidents')->pluck('kind')->all());
        $this->assertSame('expired', $attempt->fresh()->status->value);
        $this->assertSame('closed_unpaid', $attempt->order->fresh()->status->value);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_winning_suspension_race_is_preserved_with_incident_and_one_notice(): void
    {
        [$installer, $attempt] = $this->suspensionFixture('paid');
        app(InstallerInvitationService::class)->suspend($installer, User::factory()->administrator()->create());
        $this->assertSame('paid', $attempt->order->fresh()->status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseHas('financial_incidents', ['kind' => 'payment_after_suspension']);
        $this->assertDatabaseHas('payments', ['amount_cents' => $attempt->amount_cents]);
        $this->assertFalse($installer->fresh()->isActive());
    }

    public function test_suspension_with_unknown_remote_result_keeps_pending_money_history(): void
    {
        [$installer, $attempt] = $this->suspensionFixture('unknown');
        app(InstallerInvitationService::class)->suspend($installer, User::factory()->administrator()->create());
        $this->assertFalse($installer->fresh()->isActive());
        $this->assertSame('pending', $attempt->order->fresh()->status->value);
        $this->assertSame('open', $attempt->fresh()->status->value);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/checkout/sessions'));
    }
}
