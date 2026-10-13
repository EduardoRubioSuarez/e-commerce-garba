<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    public function test_token_is_delivered_once_and_hash_expires_in_seven_days(): void
    {
        $this->freezeSecond();
        $order = Order::factory()->create();
        $token = app(OrderAccessService::class)->issue($order);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        $this->assertSame(hash('sha256', $token), $order->fresh()->token_hash);
        $this->assertTrue(now()->addDays(7)->equalTo($order->fresh()->token_expires_at));
        $this->assertStringNotContainsString($token, $order->fresh()->toJson());
        $this->expectException(\LogicException::class);
        app(OrderAccessService::class)->issue($order);
    }

    public function test_lifetime_is_configurable(): void
    {
        $this->freezeSecond();
        config(['store.checkout.access_token_days' => 2]);
        $order = Order::factory()->create();
        app(OrderAccessService::class)->issue($order);
        $this->assertTrue(now()->addDays(2)->equalTo($order->fresh()->token_expires_at));
    }

    public function test_null_owner_does_not_grant_guest_access(): void
    {
        $this->assertFalse(app(OrderAccessService::class)->allows(Order::factory()->create(), null, null));
    }

    public function test_only_active_persisted_owner_is_allowed_without_token(): void
    {
        $owner = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        $access = app(OrderAccessService::class);
        $this->actingAs($owner);
        $this->assertTrue($access->allows($order, $owner, null));
        $this->assertFalse($access->allows($order, User::factory()->administrator()->create(), null));
        User::whereKey($owner->id)->update(['suspended_at' => now()]);
        $this->assertFalse($access->allows($order, $owner, null));
    }

    public function test_unsaved_order_mutation_cannot_grant_access(): void
    {
        $actor = User::factory()->installer()->create();
        $order = Order::factory()->create();
        $order->user_id = $actor->id;
        $order->token_hash = hash('sha256', str_repeat('a', 64));
        $order->token_expires_at = now()->addDay();
        $access = app(OrderAccessService::class);
        $this->assertFalse($access->allows($order, $actor, null));
        $this->assertFalse($access->allows($order, null, str_repeat('a', 64)));
    }

    public function test_valid_token_grants_hash_bound_session_for_same_order_only(): void
    {
        $order = Order::factory()->create();
        $access = app(OrderAccessService::class);
        $token = $access->issue($order);
        $this->assertTrue($access->allows($order, null, $token));
        $this->assertTrue($access->allows($order, null, null));
        $this->assertSame(['token_hash' => hash('sha256', $token)], session('orders.access.'.$order->reference));
        $other = Order::factory()->create();
        $this->assertFalse($access->allows($other, null, $token));
        $this->assertFalse($access->allows($other, null, null));
    }

    public function test_session_and_token_expire_at_exact_expiration_instant(): void
    {
        $this->freezeSecond();
        $order = Order::factory()->create();
        $access = app(OrderAccessService::class);
        $token = $access->issue($order);
        $this->assertTrue($access->allows($order, null, $token));
        $this->travel(7)->days();
        $this->assertFalse($access->allows($order, null, $token));
        $this->assertFalse($access->allows($order, null, null));
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_explicit_token_cannot_fall_back_to_session(string $token): void
    {
        $order = Order::factory()->create();
        $access = app(OrderAccessService::class);
        $this->assertTrue($access->allows($order, null, $access->issue($order)));
        $this->assertFalse($access->allows($order, null, $token));
    }

    /** @return list<array{string}> */
    public static function invalidTokens(): array
    {
        return [[''], ['wrong'], [str_repeat('0', 64)], [str_repeat('a', 63)], [str_repeat('A', 64)], [str_repeat('a', 64)."\n"]];
    }

    public function test_suspended_actor_cannot_use_token_or_session(): void
    {
        $actor = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $actor->id]);
        $access = app(OrderAccessService::class);
        $token = $access->issue($order);
        $this->actingAs($actor);
        $this->assertTrue($access->allows($order, $actor, $token));
        User::whereKey($actor->id)->update(['suspended_at' => now()]);
        $this->assertFalse($access->allows($order, $actor, $token));
        $this->assertFalse($access->allows($order, $actor, null));
    }

    public function test_token_link_redirects_to_clean_url_and_cannot_confirm_payment(): void
    {
        $order = Order::factory()->create(['contact_name' => 'Persona privada']);
        $token = app(OrderAccessService::class)->issue($order);
        $clean = '/pedidos/'.$order->reference;
        $this->get($clean.'?token='.$token.'&paid=1&session_id=cs_fake')->assertRedirect($clean)
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($clean)->assertOk()->assertSee('Persona privada')->assertSee('Pago pendiente')
            ->assertDontSee($token)->assertDontSee($order->fresh()->token_hash);
        $this->assertSame('pending', $order->fresh()->status->value);
    }

    public function test_owner_can_view_explicit_result_without_token(): void
    {
        $owner = User::factory()->installer()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);
        app(OrderAccessService::class)->issue($order);
        $response = $this->actingAs($owner)->getJson('/pedidos/'.$order->reference)->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.total_cents', 150800)
            ->assertJsonPath('data.contact.phone', '+52 614 123 4567');
        foreach (['token_hash', 'phone_canonical', 'fingerprint', 'user_id', 'base_cost_cents', 'idempotency_key', 'submission_key'] as $private) {
            $response->assertDontSee($private);
        }
    }

    public function test_unknown_foreign_and_numeric_references_have_generic_private_errors(): void
    {
        $order = Order::factory()->create(['contact_name' => 'Contacto secreto']);
        foreach ([$order->reference, (string) $order->id, (string) Str::uuid()] as $reference) {
            $this->getJson('/pedidos/'.$reference)->assertNotFound()->assertDontSee('Contacto secreto')
                ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        }
    }

    public function test_array_and_expired_tokens_do_not_disclose_contact(): void
    {
        $order = Order::factory()->create(['contact_name' => 'Contacto secreto']);
        $token = app(OrderAccessService::class)->issue($order);
        $this->getJson('/pedidos/'.$order->reference.'?token[]=malformed')->assertNotFound()->assertDontSee('Contacto secreto');
        $this->travel(8)->days();
        $this->getJson('/pedidos/'.$order->reference.'?token='.$token)->assertNotFound()->assertDontSee('Contacto secreto');
    }

    public function test_logout_removes_order_session_grant(): void
    {
        $actor = User::factory()->installer()->create();
        $order = Order::factory()->create();
        $token = app(OrderAccessService::class)->issue($order);
        $this->actingAs($actor)->get('/pedidos/'.$order->reference.'?token='.$token)->assertRedirect();
        $this->get('/pedidos/'.$order->reference)->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->get('/pedidos/'.$order->reference)->assertNotFound();
    }
}
