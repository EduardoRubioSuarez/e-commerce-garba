<?php

namespace Tests\Feature;

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CouponReservationService;
use App\Services\PricingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\MySqlTestEnvironment;
use Tests\TestCase;

#[Group('concurrency')]
class CouponConcurrencyTest extends TestCase
{
    private static bool $reportedBackend = false;

    private string $directory;

    /** @var list<Process> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        MySqlTestEnvironment::configure();
        $this->assertSame('garba_coupon_tests', DB::selectOne('SELECT DATABASE() AS name')->name);
        if (! self::$reportedBackend) {
            $backend = DB::selectOne('SELECT VERSION() AS version, @@transaction_isolation AS isolation_level');
            fwrite(STDERR, json_encode(['mysql_test_backend' => $backend]).PHP_EOL);
            self::$reportedBackend = true;
        }
        Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
        StoreSetting::factory()->create();
        $this->directory = sys_get_temp_dir().'/garba-coupon-race-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $tables = DB::select("SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('coupons','coupon_uses','orders')");
        $this->assertCount(3, $tables);
        foreach ($tables as $table) {
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
        parent::tearDown();
    }

    private function attempt(string $phone = '+52 614 123 4567', string $canonical = '+526141234567'): PaymentAttempt
    {
        return PaymentAttempt::factory()->create(['order_id' => Order::factory()->create(['phone_original' => $phone, 'phone_canonical' => $canonical])->id]);
    }

    private function worker(string $slot, string $mode, Coupon $coupon, PaymentAttempt $attempt, ?Coupon $other = null, string $submissionKey = ''): Process
    {
        $environment = ['APP_ENV' => 'testing', 'MYSQL_CONCURRENCY_TESTS' => 'true', 'MAIL_MAILER' => 'array', 'STRIPE_ENABLED' => 'false',
            'MYSQL_TEST_HOST' => (string) env('MYSQL_TEST_HOST', '127.0.0.1'), 'MYSQL_TEST_PORT' => (string) env('MYSQL_TEST_PORT', '3306'),
            'MYSQL_TEST_DATABASE' => 'garba_coupon_tests', 'MYSQL_TEST_USERNAME' => env('MYSQL_TEST_USERNAME'), 'MYSQL_TEST_PASSWORD' => env('MYSQL_TEST_PASSWORD', '')];
        $worker = new Process([PHP_BINARY, base_path('tests/coupon-concurrency-worker.php'), $this->directory, $slot, $mode, (string) $coupon->id, (string) $attempt->id, (string) ($other?->id ?? 0), $submissionKey], base_path(), $environment, null, 30);
        $worker->start();
        $this->workers[] = $worker;

        return $worker;
    }

    private function ready(string $slot): void
    {
        $deadline = microtime(true) + 20;
        while (! is_file($this->directory.'/ready-'.$slot)) {
            foreach ($this->workers as $worker) {
                if (! $worker->isRunning()) {
                    $result = json_decode($worker->getOutput(), true);
                    $this->fail('MySQL worker exited before barrier: '.json_encode([
                        'result' => $result['result'] ?? 'no_sanitized_result',
                        'class' => $result['class'] ?? null,
                        'code' => $result['code'] ?? null,
                        'exit_code' => $worker->getExitCode(),
                    ]));
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('A separate MySQL process did not reach the barrier.');
            }
            usleep(10000);
        }
    }

    /** @param list<Process> $workers
     * @return list<array<string, mixed>>
     */
    private function results(array $workers): array
    {
        touch($this->directory.'/go');
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), 'Separate MySQL worker failed; inspect its sanitized result.');
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        if (count($results) > 1) {
            $this->assertCount(count($results), array_unique(array_column($results, 'pid')));
        }

        return $results;
    }

    public function test_double_checkout_confirmation_creates_one_new_order_and_attempt(): void
    {
        $coupon = Coupon::factory()->create();
        $coupon->products()->attach(Product::factory()->published()->create(['base_cost_cents' => 100000]));
        $fixture = $this->attempt();
        $key = (string) Str::uuid();
        $one = $this->worker('1', 'checkout', $coupon, $fixture, submissionKey: $key);
        $two = $this->worker('2', 'checkout', $coupon, $fixture, submissionKey: $key);
        $this->ready('1');
        $this->ready('2');
        $results = $this->results([$one, $two]);
        $this->assertSame($results[0]['attempt_id'], $results[1]['attempt_id']);
        $this->assertNotSame($fixture->id, $results[0]['attempt_id']);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('coupon_uses', 0);
    }

    public function test_checkout_recovery_sees_attempt_committed_after_outer_snapshot(): void
    {
        $coupon = Coupon::factory()->create();
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon->products()->attach($product);
        $fixture = $this->attempt();
        $key = (string) Str::uuid();
        $worker = $this->worker('1', 'snapshot-checkout', $coupon, $fixture, submissionKey: $key);
        $this->ready('1');
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        $order = $fixture->order;
        $attempt = app(CheckoutService::class)->start($lines, new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical), null, $quote->fingerprint, $key);
        $results = $this->results([$worker]);
        $this->assertSame($attempt->id, $results[0]['attempt_id']);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_checkout_with_coupon_rolls_back_losing_purchase_for_last_slot(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $coupon->products()->attach(Product::factory()->published()->create(['base_cost_cents' => 100000]));
        $one = $this->worker('1', 'coupon-checkout', $coupon, $this->attempt(), submissionKey: (string) Str::uuid());
        $two = $this->worker('2', 'coupon-checkout', $coupon, $this->attempt('6147654321', '+526147654321'), submissionKey: (string) Str::uuid());
        $this->ready('1');
        $this->ready('2');
        $results = $this->results([$one, $two]);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['result'] === 'success')));
        $this->assertDatabaseCount('orders', 3);
        $this->assertDatabaseCount('payment_attempts', 3);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('coupon_uses', 1);
        $this->assertDatabaseCount('coupon_use_histories', 1);
    }

    public function test_coupon_recovery_under_old_snapshot_ignores_renamed_coupon_rules(): void
    {
        $coupon = Coupon::factory()->create();
        $product = Product::factory()->published()->create(['base_cost_cents' => 100000]);
        $coupon->products()->attach($product);
        $fixture = $this->attempt();
        $key = (string) Str::uuid();
        $worker = $this->worker('1', 'snapshot-coupon-checkout', $coupon, $fixture, submissionKey: $key);
        $this->ready('1');
        $lines = [new CartLineData($product->id, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $coupon);
        $order = $fixture->order;
        $attempt = app(CheckoutService::class)->start($lines, new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical), null, $quote->fingerprint, $key, $coupon->code);
        $coupon->update(['code' => 'RENAMEDPROMO', 'value' => 2000, 'is_active' => false]);
        $results = $this->results([$worker]);
        $this->assertSame('success', $results[0]['result']);
        $this->assertSame($attempt->id, $results[0]['attempt_id']);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('payment_attempts', 2);
        $this->assertDatabaseCount('coupon_uses', 1);
        $this->assertSame(135720, $attempt->fresh()->amount_cents);
    }

    public function test_equivalent_phones_compete_for_one_permanent_right(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 2]);
        $one = $this->worker('1', 'reserve', $coupon, $this->attempt());
        $two = $this->worker('2', 'reserve', $coupon, $this->attempt('614 123 4567'));
        $this->ready('1');
        $this->ready('2');
        $results = $this->results([$one, $two]);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['result'] === 'success')));
        $this->assertDatabaseCount('coupon_uses', 1);
        $this->assertDatabaseCount('coupon_use_histories', 1);
    }

    public function test_different_phones_compete_for_the_last_slot(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $one = $this->worker('1', 'reserve', $coupon, $this->attempt());
        $two = $this->worker('2', 'reserve', $coupon, $this->attempt('+52 614 765 4321', '+526147654321'));
        $this->ready('1');
        $this->ready('2');
        $results = $this->results([$one, $two]);
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['result'] === 'success')));
        $this->assertSame(1, app(CouponReservationService::class)->occupied($coupon));
    }

    public function test_payment_racing_expiration_preserves_money_and_never_overconsumes(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $attempt = $this->attempt();
        $order = $attempt->order;
        DB::transaction(fn () => app(CouponReservationService::class)->reserve($coupon, new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical), $order, $attempt));
        $one = $this->worker('1', 'consume', $coupon, $attempt);
        $two = $this->worker('2', 'release', $coupon, $attempt);
        $this->ready('1');
        $this->ready('2');
        $this->results([$one, $two]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertLessThanOrEqual(1, app(CouponReservationService::class)->occupied($coupon));
        $this->assertContains(CouponUse::query()->sole()->status->value, ['free', 'consumed']);
        $this->assertGreaterThanOrEqual(1, DB::table('financial_incidents')->count());
    }

    public function test_deadlock_retries_preserve_attempt_ids_and_single_histories(): void
    {
        $a = Coupon::factory()->create();
        $b = Coupon::factory()->create();
        $first = $this->attempt();
        $second = $this->attempt('+52 614 765 4321', '+526147654321');
        $one = $this->worker('1', 'deadlock', $a, $first, $b);
        $two = $this->worker('2', 'deadlock', $b, $second, $a);
        $this->ready('1');
        $this->ready('2');
        $results = $this->results([$one, $two]);
        $this->assertContains(2, array_column($results, 'tries'));
        $this->assertSame([$first->id, $second->id], array_column($results, 'attempt_id'));
        $this->assertDatabaseCount('coupon_uses', 2);
        $this->assertDatabaseCount('coupon_use_histories', 2);
    }

    public function test_payment_winning_real_suspension_race_preserves_money_with_incident(): void
    {
        User::factory()->administrator()->create();
        $owner = User::factory()->installer()->create();
        $attempt = PaymentAttempt::factory()->create(['order_id' => Order::factory()->create(['user_id' => $owner->id])->id]);
        $coupon = Coupon::factory()->create();
        $suspension = $this->worker('1', 'suspend', $coupon, $attempt);
        $payment = $this->worker('2', 'pay-during-suspension', $coupon, $attempt);
        $this->ready('1');
        $this->ready('2');
        $this->results([$suspension, $payment]);
        $this->assertFalse($owner->fresh()->isActive());
        $this->assertSame('paid', $attempt->order->fresh()->status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseHas('financial_incidents', ['kind' => 'payment_after_suspension']);
    }

    public function test_payment_consumes_reservation_committed_after_its_transaction_snapshot(): void
    {
        $coupon = Coupon::factory()->create();
        $attempt = $this->attempt();
        $worker = $this->worker('1', 'snapshot-payment', $coupon, $attempt);
        $this->ready('1');
        $order = $attempt->order;
        DB::transaction(fn () => app(CouponReservationService::class)->reserve($coupon,
            new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical), $order, $attempt));
        $this->results([$worker]);
        $this->assertSame('consumed', CouponUse::query()->sole()->status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('coupon_use_histories', 2);
    }

    public function test_capacity_uses_current_reads_inside_an_older_repeatable_read_snapshot(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $worker = $this->worker('1', 'snapshot', $coupon, $this->attempt('+52 614 765 4321', '+526147654321'));
        $this->ready('1');
        $attempt = $this->attempt();
        $order = $attempt->order;
        DB::transaction(fn () => app(CouponReservationService::class)->reserve($coupon, new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical), $order, $attempt));
        $results = $this->results([$worker]);
        $this->assertSame('rejected', $results[0]['result']);
        $this->assertDatabaseCount('coupon_uses', 1);
    }
}
