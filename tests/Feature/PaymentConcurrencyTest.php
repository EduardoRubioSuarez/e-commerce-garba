<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\NotificationAttempt;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\StripeEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\MySqlTestEnvironment;
use Tests\TestCase;

#[Group('concurrency')]
class PaymentConcurrencyTest extends TestCase
{
    private string $directory;

    /** @var list<Process> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        MySqlTestEnvironment::configure();
        $backend = DB::selectOne('SELECT DATABASE() AS name, VERSION() AS version, @@transaction_isolation AS isolation_level');
        $this->assertSame('garba_coupon_tests', $backend->name);
        $this->assertSame('REPEATABLE-READ', $backend->isolation_level);
        Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        StoreSetting::factory()->create();
        $this->directory = sys_get_temp_dir().'/garba-payment-race-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $engines = DB::select("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('orders','payment_attempts','payments','order_notifications')");
        $this->assertCount(4, $engines);
        foreach ($engines as $table) {
            $this->assertSame('InnoDB', $table->ENGINE);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function worker(string $slot, string $mode, PaymentAttempt $attempt, int $target = 0, string $suffix = 'fixture'): Process
    {
        $environment = ['APP_ENV' => 'testing', 'MYSQL_CONCURRENCY_TESTS' => 'true', 'MAIL_MAILER' => 'array', 'STRIPE_ENABLED' => 'false',
            'MYSQL_TEST_HOST' => (string) env('MYSQL_TEST_HOST', '127.0.0.1'), 'MYSQL_TEST_PORT' => (string) env('MYSQL_TEST_PORT', '3306'),
            'MYSQL_TEST_DATABASE' => 'garba_coupon_tests', 'MYSQL_TEST_USERNAME' => env('MYSQL_TEST_USERNAME'), 'MYSQL_TEST_PASSWORD' => env('MYSQL_TEST_PASSWORD', '')];
        $worker = new Process([PHP_BINARY, base_path('tests/payment-concurrency-worker.php'), $this->directory, $slot, $mode,
            (string) $attempt->id, (string) $target, $suffix], base_path(), $environment, null, 40);
        $worker->start();
        $this->workers[] = $worker;

        return $worker;
    }

    private function waitFor(string $file): void
    {
        $deadline = microtime(true) + 25;
        while (! is_file($this->directory.'/'.$file)) {
            foreach ($this->workers as $worker) {
                if (! $worker->isRunning() && ! $worker->isSuccessful()) {
                    $result = json_decode($worker->getOutput(), true);
                    $this->fail('Financial worker failed: '.json_encode(['result' => $result['result'] ?? 'no_result', 'class' => $result['class'] ?? null, 'code' => $result['code'] ?? null]));
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('Financial worker barrier timed out: '.$file);
            }
            usleep(10000);
        }
    }

    /** @return list<array<string, mixed>> */
    private function race(PaymentAttempt $attempt, string $one, string $two, int $targetOne = 0, int $targetTwo = 0, string $suffixTwo = 'fixture'): array
    {
        $this->worker('1', $one, $attempt, $targetOne);
        $this->worker('2', $two, $attempt, $targetTwo, $suffixTwo);
        $this->waitFor('ready-1');
        $this->waitFor('ready-2');
        DB::beginTransaction();
        Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
        touch($this->directory.'/go');
        $this->waitFor('entered-1');
        $this->waitFor('entered-2');
        DB::commit();
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertTrue($worker->isSuccessful(), 'Financial worker failed: '.json_encode($result));
            $this->assertSame('ok', $result['result']);
            $results[] = $result;
        }
        $this->assertNotSame($results[0]['pid'], $results[1]['pid']);
        $this->assertSame([0, 0], array_column($results, 'http_inside_transaction'));
        $this->assertSame([0, 0], array_column($results, 'mail_inside_transaction'));

        return $results;
    }

    private function attempt(): PaymentAttempt
    {
        $attempt = PaymentAttempt::factory()->create(['status' => PaymentAttemptStatus::Open, 'stripe_session_id' => 'cs_test_fixture']);
        OrderLine::factory()->create(['order_id' => $attempt->order_id]);

        return $attempt;
    }

    private function event(string $id): StripeEvent
    {
        return StripeEvent::factory()->create(['stripe_event_id' => $id, 'type' => 'checkout.session.completed', 'status' => 'pending']);
    }

    private function assertOnePaidEffect(PaymentAttempt $attempt): void
    {
        $this->assertSame(OrderStatus::Paid, $attempt->order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertSame(NotificationStatus::Accepted, OrderNotification::query()->sole()->status);
    }

    public function test_different_events_for_same_charge_produce_one_financial_effect(): void
    {
        $attempt = $this->attempt();
        $first = $this->event('evt_one');
        $second = $this->event('evt_two');
        $this->race($attempt, 'apply', 'apply', $first->id, $second->id);
        $this->assertOnePaidEffect($attempt);
        $this->assertSame(2, StripeEvent::query()->where('status', 'processed')->count());
    }

    public function test_webhook_and_cron_preserve_one_charge_and_one_notice(): void
    {
        $attempt = $this->attempt();
        $event = $this->event('evt_webhook');
        $event->payload = ['api_version' => config('services.stripe.api_version'), 'livemode' => false, 'data' => ['object' => ['id' => 'cs_test_fixture']]];
        $event->save();
        $this->race($attempt, 'webhook', 'cron', $event->id);
        $this->assertOnePaidEffect($attempt);
        $this->assertSame('processed', $event->fresh()->status);
    }

    public function test_payment_racing_expiration_keeps_money_and_records_contradiction(): void
    {
        $attempt = $this->attempt();
        $this->race($attempt, 'apply', 'expire');
        $this->assertOnePaidEffect($attempt);
        $this->assertSame(1, DB::table('financial_incidents')->count());
        $this->assertSame(PaymentAttemptStatus::Paid, $attempt->fresh()->status);
    }

    public function test_two_distinct_charges_remain_visible_without_duplicate_purchase_notice(): void
    {
        $attempt = $this->attempt();
        $this->race($attempt, 'apply', 'apply', suffixTwo: 'second');
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(301600, (int) Payment::query()->sum('amount_cents'));
        $this->assertDatabaseHas('financial_incidents', ['kind' => 'duplicate_charge']);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('notification_attempts', 1);
    }

    public function test_simultaneous_pickup_requests_create_one_notice_and_one_send(): void
    {
        $attempt = $this->attempt();
        $attempt->order->forceFill(['status' => OrderStatus::Paid])->save();
        Payment::factory()->create(['order_id' => $attempt->order_id, 'payment_attempt_id' => $attempt->id]);
        $admin = User::factory()->administrator()->create();
        $this->race($attempt, 'pickup', 'pickup', $admin->id, $admin->id);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertSame('pickup', OrderNotification::query()->sole()->type);
        $this->assertSame(NotificationStatus::Accepted, OrderNotification::query()->sole()->status);
    }

    public function test_cron_and_request_cannot_claim_the_same_notice_twice(): void
    {
        $attempt = $this->attempt();
        $attempt->order->forceFill(['status' => OrderStatus::Paid])->save();
        $notice = OrderNotification::factory()->create(['order_id' => $attempt->order_id, 'type' => 'purchase', 'status' => NotificationStatus::Pending]);
        $results = $this->race($attempt, 'notifications-cron', 'dispatch', $notice->id, $notice->id);
        $this->assertSame(1, array_sum(array_column($results, 'sent')));
        $this->assertDatabaseCount('notification_attempts', 1);
        $this->assertSame('accepted', NotificationAttempt::query()->sole()->status);
    }

    public function test_double_checkout_under_external_repeatable_read_snapshot_creates_one_purchase(): void
    {
        $attempt = $this->attempt();
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $results = $this->race($attempt, 'snapshot-checkout', 'snapshot-checkout', $product->id, $product->id);
        $this->assertSame($results[0]['attempt_id'], $results[1]['attempt_id']);
        $this->assertNotSame($attempt->id, $results[0]['attempt_id']);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_simultaneous_remote_resumptions_keep_original_attempt_and_idempotency_key(): void
    {
        $attempt = $this->attempt();
        $attempt->forceFill(['status' => PaymentAttemptStatus::Unknown, 'stripe_session_id' => null, 'first_sent_at' => now()->subMinute(),
            'checkout_parameters' => ['mode' => 'payment', 'expires_at' => now()->addMinutes(29)->timestamp]])->save();
        $key = $attempt->idempotency_key;
        $this->race($attempt, 'resume', 'resume');
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame($key, $attempt->fresh()->idempotency_key);
        $this->assertSame('cs_test_fixture', $attempt->fresh()->stripe_session_id);
        $this->assertSame(PaymentAttemptStatus::Open, $attempt->fresh()->status);
    }
}
