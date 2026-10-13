<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\OrderAccessService;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddedCheckoutTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.stripe.enabled' => true,
            'services.stripe.secret' => 'sk_test_fixture', 'services.stripe.publishable_key' => 'pk_test_fixture',
            'services.stripe.account_id' => 'acct_fixture']);
        Http::preventStrayRequests();
    }

    private function attempt(bool $guest = false): PaymentAttempt
    {
        $owner = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $guest ? null : $owner->id]);
        $attempt = PaymentAttempt::factory()->create(['order_id' => $order->id, 'status' => PaymentAttemptStatus::Open,
            'stripe_session_id' => 'cs_test_embedded', 'expires_at' => now()->addMinutes(31),
            'first_sent_at' => now(), 'checkout_parameters' => ['ui_mode' => 'embedded_page']]);
        if (! $guest) {
            $this->actingAs($owner);
        }

        return $attempt;
    }

    /** @return array<string,mixed> */
    private function remoteSession(PaymentAttempt $attempt): array
    {
        return ['id' => 'cs_test_embedded', 'object' => 'checkout.session', 'mode' => 'payment', 'ui_mode' => 'embedded_page',
            'status' => 'open', 'payment_status' => 'unpaid', 'livemode' => false, 'url' => null,
            'amount_total' => $attempt->amount_cents, 'currency' => 'mxn', 'expires_at' => now()->addMinutes(31)->timestamp,
            'client_secret' => 'cs_test_embedded_secret_fixture', 'payment_intent' => null,
            'metadata' => ['order_reference' => $attempt->order->reference, 'attempt_reference' => $attempt->reference]];
    }

    public function test_new_checkout_uses_embedded_mode_without_hosted_redirects(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        Http::fake(function ($request) use ($attempt) {
            $this->assertSame('embedded_page', $request['ui_mode']);
            $this->assertSame('never', $request['redirect_on_completion']);
            $this->assertArrayNotHasKey('success_url', $request->data());
            $this->assertArrayNotHasKey('cancel_url', $request->data());

            return Http::response($this->remoteSession($attempt));
        });
        $session = app(StripeGateway::class)->createCheckout($attempt);
        $this->assertNull($session->url);
        $this->assertSame(PaymentAttemptStatus::Open, $attempt->fresh()->status);
        $this->assertStringNotContainsString('secret_fixture', json_encode($attempt->fresh()));
    }

    public function test_owner_page_mounts_checkout_without_exposing_client_secret_in_html_or_order_json(): void
    {
        $attempt = $this->attempt();
        $url = route('orders.show', ['order' => $attempt->order->reference]);
        $this->get($url)->assertOk()->assertSee('data-stripe-checkout', false)->assertSee('Paga con tarjeta')
            ->assertSee('Actualizar estado del pedido')->assertSee('Pago pendiente')
            ->assertDontSee('Referencia:')->assertDontSee('Iniciar nueva compra')
            ->assertDontSee('No hay un pago confirmado para este pedido')
            ->assertDontSee('Continuar al pago seguro')->assertDontSee('secret_fixture')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson($url)->assertOk()->assertDontSee('client_secret');
        Http::assertNothingSent();
    }

    public function test_secret_is_returned_only_to_owner_with_no_store_and_without_creating_another_attempt(): void
    {
        $attempt = $this->attempt();
        Http::fake(['*' => Http::response($this->remoteSession($attempt))]);
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertOk()
            ->assertJsonPath('clientSecret', 'cs_test_embedded_secret_fixture')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_foreign_user_and_suspended_owner_cannot_obtain_secret(): void
    {
        $attempt = $this->attempt();
        $url = '/pedidos/'.$attempt->order->reference.'/formulario-pago';
        $this->actingAs(User::factory()->installer()->create())->postJson($url)->assertNotFound();
        $owner = $attempt->order->user;
        $owner->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($owner)->postJson($url)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_guest_with_order_access_can_obtain_secret(): void
    {
        $attempt = $this->attempt(true);
        $token = app(OrderAccessService::class)->issue($attempt->order);
        $this->get('/pedidos/'.$attempt->order->reference.'?token='.$token)->assertRedirect();
        Http::fake(['*' => Http::response($this->remoteSession($attempt))]);
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertOk();
    }

    public function test_paid_and_expired_attempts_do_not_expose_payment_form_or_call_stripe(): void
    {
        $attempt = $this->attempt();
        $attempt->order->forceFill(['status' => OrderStatus::Paid])->save();
        $url = '/pedidos/'.$attempt->order->reference;
        $this->get($url)->assertOk()->assertDontSee('data-stripe-checkout', false);
        $this->postJson($url.'/formulario-pago')->assertStatus(409);
        $attempt->order->forceFill(['status' => OrderStatus::Pending])->save();
        $attempt->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->postJson($url.'/formulario-pago')->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_remote_mismatch_and_completed_session_never_release_client_secret(): void
    {
        $attempt = $this->attempt();
        $fixture = $this->remoteSession($attempt);
        $fixture['amount_total']++;
        Http::fake(['*' => Http::response($fixture)]);
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertStatus(503)->assertDontSee('secret_fixture');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_remote_completed_payment_waits_for_webhook_without_releasing_secret(): void
    {
        $attempt = $this->attempt();
        $fixture = $this->remoteSession($attempt);
        $fixture['status'] = 'complete';
        $fixture['payment_status'] = 'paid';
        Http::fake(['*' => Http::response($fixture)]);
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertStatus(409)->assertDontSee('secret_fixture');
        $this->assertSame(OrderStatus::Pending, $attempt->order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_publishable_key_does_not_load_checkout_or_contact_stripe(): void
    {
        $attempt = $this->attempt();
        config(['services.stripe.publishable_key' => 'pk_live_wrong_mode']);
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_misconfigured_secret_key_is_never_rendered_as_publishable_key(): void
    {
        $attempt = $this->attempt();
        config(['services.stripe.publishable_key' => 'sk_test_do_not_expose']);
        $this->get('/pedidos/'.$attempt->order->reference)->assertOk()->assertDontSee('sk_test_do_not_expose');
    }

    public function test_revocation_during_remote_lookup_prevents_secret_delivery(): void
    {
        $attempt = $this->attempt();
        Http::fake(function () use ($attempt) {
            $attempt->order->user->forceFill(['auth_version' => 1])->save();

            return Http::response($this->remoteSession($attempt));
        });
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertStatus(409)->assertDontSee('secret_fixture');
    }

    public function test_payment_confirmation_during_lookup_prevents_secret_delivery(): void
    {
        $attempt = $this->attempt();
        Http::fake(function () use ($attempt) {
            $attempt->order->forceFill(['status' => OrderStatus::Paid])->save();

            return Http::response($this->remoteSession($attempt));
        });
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertStatus(409)->assertDontSee('secret_fixture');
    }

    public function test_newer_attempt_during_lookup_prevents_secret_delivery(): void
    {
        $attempt = $this->attempt();
        Http::fake(function () use ($attempt) {
            PaymentAttempt::factory()->create(['order_id' => $attempt->order_id]);

            return Http::response($this->remoteSession($attempt));
        });
        $this->postJson('/pedidos/'.$attempt->order->reference.'/formulario-pago')->assertStatus(409)->assertDontSee('secret_fixture');
    }

    public function test_legacy_hosted_session_keeps_original_parameters_and_link(): void
    {
        $attempt = $this->attempt();
        $attempt->forceFill(['checkout_parameters' => ['mode' => 'payment', 'success_url' => 'https://example.test/result'],
            'checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_legacy'])->save();
        $original = $attempt->checkout_parameters;
        $this->get('/pedidos/'.$attempt->order->reference)->assertOk()->assertSee('Continuar al pago seguro')->assertDontSee('data-stripe-checkout', false);
        app(StripeGateway::class)->createCheckout($attempt);
        $this->assertSame($original, $attempt->fresh()->checkout_parameters);
        Http::assertNothingSent();
    }
}
