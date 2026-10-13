<?php

namespace Tests\Feature;

use App\Models\StripeEvent;
use App\Services\StripeWebhookVerifier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => 'whsec_fixture', 'services.stripe.account_id' => 'acct_fixture', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Http::preventStrayRequests();
    }

    private function body(string $type = 'checkout.session.completed'): string
    {
        return json_encode(['id' => 'evt_fixture', 'object' => 'event', 'api_version' => '2026-08-26.dahlia', 'created' => now()->timestamp,
            'livemode' => false, 'type' => $type, 'data' => ['object' => ['id' => 'cs_test_fixture']]], JSON_THROW_ON_ERROR);
    }

    private function signature(string $body, ?int $timestamp = null): string
    {
        $timestamp ??= now()->timestamp;

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_fixture');
    }

    private function webhook(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
    }

    public function test_signature_uses_original_body_and_multiple_v1(): void
    {
        $body = $this->body();
        $verified = app(StripeWebhookVerifier::class)->verify($body, 'v1='.str_repeat('0', 64).','.$this->signature($body));
        $this->assertSame('evt_fixture', $verified['id']);
        $this->webhook($body.' ', $this->signature($body))->assertStatus(400);
        $this->assertDatabaseCount('stripe_events', 0);
    }

    public function test_persistence_is_durable_and_duplicate_does_not_duplicate_work(): void
    {
        $body = $this->body();
        $this->webhook($body, $this->signature($body))->assertOk();
        $this->webhook($body, $this->signature($body))->assertOk();
        $this->assertDatabaseCount('stripe_events', 1);
        $event = StripeEvent::query()->sole();
        $this->assertSame('pending', $event->status);
        $this->assertSame($body, $event->raw_body);
        Http::assertNothingSent();
    }

    public function test_unhandled_type_is_preserved_as_ignored(): void
    {
        $body = $this->body('customer.created');
        $this->webhook($body, $this->signature($body))->assertOk();
        $this->assertSame('ignored', StripeEvent::query()->sole()->status);
    }

    public function test_cancelled_database_save_cannot_acknowledge_event(): void
    {
        StripeEvent::creating(fn () => false);
        $body = $this->body();
        $this->webhook($body, $this->signature($body))->assertStatus(503);
        $this->assertDatabaseCount('stripe_events', 0);
    }

    #[DataProvider('invalidTimestamps')]
    public function test_timestamp_tolerance_is_enforced(int $offset, int $expected): void
    {
        $body = $this->body('customer.created');
        $this->webhook($body, $this->signature($body, now()->timestamp + $offset))->assertStatus($expected);
    }

    public static function invalidTimestamps(): array
    {
        return [[-301, 400], [301, 400], [-300, 200], [300, 200]];
    }

    public function test_unsigned_webhook_and_other_posts_are_csrf_protected(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->webhook($this->body(), 'invalid')->assertStatus(400);
        $this->post('/checkout', [])->assertStatus(419);
    }
}
