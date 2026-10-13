<?php

namespace Tests\Feature;

use App\Data\ContactData;
use App\Data\VerifiedPaymentResult;
use App\Enums\CouponUseStatus;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\CouponUseHistory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CouponReservationService;
use App\Services\PaymentResultApplier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CouponReservationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'services.stripe.account_id' => 'acct_fixture', 'services.stripe.livemode' => false]);
        StoreSetting::factory()->create();
    }

    private function reserve(Coupon $coupon, ?PaymentAttempt $attempt = null): CouponUse
    {
        $attempt ??= PaymentAttempt::factory()->create();
        $order = $attempt->order;
        $contact = new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical);

        return DB::transaction(fn () => app(CouponReservationService::class)->reserve($coupon, $contact, $order, $attempt));
    }

    private function evidence(PaymentAttempt $attempt, string $state = 'expired'): VerifiedPaymentResult
    {
        return new VerifiedPaymentResult($attempt->reference, $attempt->order->reference, 'stripe_api', 'evt_fixture',
            'acct_fixture', false, $attempt->currency, $attempt->amount_cents, $state, $attempt->stripe_session_id);
    }

    public function test_reservation_is_idempotent_for_the_same_attempt_and_preserves_unique_pair(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $attempt = PaymentAttempt::factory()->create();
        $use = $this->reserve($coupon, $attempt);
        $this->assertSame($use->id, $this->reserve($coupon, $attempt)->id);
        $this->assertSame(CouponUseStatus::Reserved, $use->fresh()->status);
        $this->assertDatabaseCount('coupon_uses', 1);
        $this->assertDatabaseCount('coupon_use_histories', 1);
        $this->expectException(ValidationException::class);
        $this->reserve($coupon);
    }

    public function test_order_preserves_coupon_definition_and_right_when_admin_edits_coupon(): void
    {
        $coupon = Coupon::factory()->create(['value' => 1000]);
        $attempt = PaymentAttempt::factory()->create();
        $use = $this->reserve($coupon, $attempt);
        $order = $attempt->order->fresh();
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertSame($use->id, $order->coupon_use_id);
        $this->assertSame(1000, $order->coupon_snapshot['value']);
        $coupon->update(['value' => 2000]);
        $this->assertSame(1000, $order->fresh()->coupon_snapshot['value']);
        $this->expectException(\LogicException::class);
        $order->forceFill(['coupon_snapshot' => ['value' => 2000]])->save();
    }

    public function test_definitive_failure_before_first_remote_request_can_release_reservation(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $use = $this->reserve(Coupon::factory()->create(), $attempt);
        $attempt->forceFill(['status' => 'failed', 'stripe_session_id' => null, 'first_sent_at' => null])->save();
        $evidence = new VerifiedPaymentResult($attempt->reference, $attempt->order->reference, 'local_definitive', 'failure_fixture',
            'acct_fixture', false, $attempt->currency, $attempt->amount_cents, 'failed');
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $evidence));
        $this->assertSame(CouponUseStatus::Free, $use->fresh()->status);
        $this->assertDatabaseCount('coupon_use_histories', 2);
    }

    public function test_expiration_from_old_attempt_cannot_release_reused_right(): void
    {
        $coupon = Coupon::factory()->create();
        $first = PaymentAttempt::factory()->create();
        $use = $this->reserve($coupon, $first);
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $this->evidence($first)));
        $next = PaymentAttempt::factory()->create();
        $this->reserve($coupon, $next);
        app(PaymentResultApplier::class)->apply($first->fresh(), $this->evidence($first));
        $this->assertSame(CouponUseStatus::Reserved, $use->fresh()->status);
        $this->assertSame($next->id, $use->fresh()->payment_attempt_id);
        $this->assertSame('closed_unpaid', $first->order->fresh()->status->value);
    }

    public function test_paid_old_attempt_preserves_money_without_consuming_reused_right(): void
    {
        Mail::fake();
        $coupon = Coupon::factory()->create();
        $first = PaymentAttempt::factory()->create();
        $use = $this->reserve($coupon, $first);
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $this->evidence($first)));
        $next = PaymentAttempt::factory()->create();
        $this->reserve($coupon, $next);
        $paid = new VerifiedPaymentResult($first->reference, $first->order->reference, 'stripe_api', 'evt_old',
            'acct_fixture', false, $first->currency, $first->amount_cents, 'paid', null, 'pi_old', 'ch_old');
        app(PaymentResultApplier::class)->apply($first->fresh(), $paid);
        $this->assertSame(CouponUseStatus::Reserved, $use->fresh()->status);
        $this->assertSame($next->id, $use->fresh()->payment_attempt_id);
        $this->assertSame('paid', $first->order->fresh()->status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('financial_incidents', ['kind' => 'coupon_reservation_conflict']);
    }

    public function test_failed_attempt_already_sent_remotely_cannot_use_local_definitive_release(): void
    {
        $attempt = PaymentAttempt::factory()->create();
        $use = $this->reserve(Coupon::factory()->create(), $attempt);
        $attempt->forceFill(['status' => 'failed', 'first_sent_at' => now()])->save();
        $evidence = new VerifiedPaymentResult($attempt->reference, $attempt->order->reference, 'local_definitive', 'failure_fixture',
            'acct_fixture', false, $attempt->currency, $attempt->amount_cents, 'failed');
        try {
            DB::transaction(fn () => app(CouponReservationService::class)->release($use, $evidence));
            $this->fail('Unknown remote session cannot be released through local evidence.');
        } catch (ValidationException) {
            $this->assertSame(CouponUseStatus::Reserved, $use->fresh()->status);
        }
    }

    public function test_capacity_counts_reservations_and_consumed_uses(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $use = $this->reserve($coupon);
        $payment = Payment::factory()->create(['order_id' => $use->order_id, 'payment_attempt_id' => $use->payment_attempt_id]);
        DB::transaction(fn () => app(CouponReservationService::class)->consume($use, $payment));
        DB::transaction(fn () => app(CouponReservationService::class)->consume($use, $payment));
        $this->assertSame(CouponUseStatus::Consumed, $use->fresh()->status);
        $this->assertDatabaseCount('coupon_use_histories', 2);
        $attempt = PaymentAttempt::factory()->create(['order_id' => Order::factory()->create(['phone_original' => '+52 614 765 4321', 'phone_canonical' => '+526147654321'])->id]);
        $this->expectException(ValidationException::class);
        $this->reserve($coupon, $attempt);
    }

    public function test_verified_expiration_frees_capacity_but_keeps_pair_and_history(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 1]);
        $use = $this->reserve($coupon);
        $attempt = PaymentAttempt::findOrFail($use->payment_attempt_id);
        $evidence = $this->evidence($attempt);
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $evidence));
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $evidence));
        $this->assertSame(CouponUseStatus::Free, $use->fresh()->status);
        $this->assertDatabaseCount('coupon_use_histories', 2);
        $next = $this->reserve($coupon);
        $this->assertSame($use->id, $next->id);
        $this->assertDatabaseCount('coupon_uses', 1);
        $this->assertDatabaseCount('coupon_use_histories', 3);
    }

    #[DataProvider('unsafeEvidence')]
    public function test_local_time_or_incompatible_evidence_cannot_release(array $changes): void
    {
        $use = $this->reserve(Coupon::factory()->create());
        $attempt = PaymentAttempt::findOrFail($use->payment_attempt_id);
        $data = array_replace(get_object_vars($this->evidence($attempt)), $changes);
        try {
            DB::transaction(fn () => app(CouponReservationService::class)->release($use, new VerifiedPaymentResult(...$data)));
            $this->fail('Unsafe evidence released a reservation.');
        } catch (ValidationException) {
            $this->assertSame(CouponUseStatus::Reserved, $use->fresh()->status);
            $this->assertDatabaseCount('coupon_use_histories', 1);
        }
    }

    public static function unsafeEvidence(): array
    {
        return [
            [['state' => 'unknown']], [['state' => 'open']], [['state' => 'paid']], [['source' => 'local_clock']],
            [['accountId' => 'acct_foreign']], [['livemode' => true]], [['amountCents' => 1]], [['currency' => 'USD']],
            [['attemptReference' => 'foreign']], [['orderReference' => 'foreign']], [['problems' => ['unverified']]],
        ];
    }

    public function test_consumed_coupon_never_becomes_free_on_expiration_or_refund(): void
    {
        $use = $this->reserve(Coupon::factory()->create());
        $payment = Payment::factory()->create(['order_id' => $use->order_id, 'payment_attempt_id' => $use->payment_attempt_id]);
        DB::transaction(fn () => app(CouponReservationService::class)->consume($use, $payment));
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $this->evidence(PaymentAttempt::findOrFail($payment->payment_attempt_id))));
        $this->assertSame(CouponUseStatus::Consumed, $use->fresh()->status);
        $this->expectException(\LogicException::class);
        $use->delete();
    }

    public function test_a_free_pair_cannot_be_consumed_by_its_previous_attempt(): void
    {
        $coupon = Coupon::factory()->create();
        $use = $this->reserve($coupon);
        $attempt = PaymentAttempt::findOrFail($use->payment_attempt_id);
        DB::transaction(fn () => app(CouponReservationService::class)->release($use, $this->evidence($attempt)));
        $payment = Payment::factory()->create(['order_id' => $use->order_id, 'payment_attempt_id' => $attempt->id]);
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(CouponReservationService::class)->consume($use, $payment));
    }

    public function test_admin_cannot_reduce_limit_below_reserved_and_consumed(): void
    {
        $coupon = Coupon::factory()->create(['max_uses' => 2]);
        $this->reserve($coupon);
        $other = PaymentAttempt::factory()->create(['order_id' => Order::factory()->create(['phone_original' => '+52 614 765 4321', 'phone_canonical' => '+526147654321'])->id]);
        $this->reserve($coupon, $other);
        $this->actingAs(User::factory()->administrator()->create())->put(route('admin.coupons.update', $coupon), [
            'code' => $coupon->code, 'kind' => 'percentage', 'value' => '10', 'currency' => 'MXN',
            'minimum_base' => '0', 'max_uses' => 1, 'scope' => 'all',
        ])->assertSessionHasErrors('max_uses');
        $this->assertSame(2, $coupon->fresh()->max_uses);
    }

    public function test_equivalent_phone_formats_cannot_claim_the_same_coupon_again(): void
    {
        $coupon = Coupon::factory()->create();
        $this->reserve($coupon);
        $order = Order::factory()->create(['phone_original' => '614 123 4567']);
        $attempt = PaymentAttempt::factory()->create(['order_id' => $order->id]);
        $this->expectException(ValidationException::class);
        $this->reserve($coupon, $attempt);
    }

    public function test_history_failure_rolls_back_the_reservation_and_preserves_capacity(): void
    {
        CouponUseHistory::creating(fn () => false);
        try {
            $this->reserve(Coupon::factory()->create());
            $this->fail('Reservation persisted without history.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('coupon_uses', 0);
            $this->assertDatabaseCount('coupon_use_histories', 0);
        } finally {
            CouponUseHistory::flushEventListeners();
        }
    }

    public function test_foreign_payment_cannot_consume_a_reservation(): void
    {
        $use = $this->reserve(Coupon::factory()->create());
        $payment = Payment::factory()->create();
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => app(CouponReservationService::class)->consume($use, $payment));
    }

    public function test_payment_and_refund_consume_once_in_the_common_financial_transaction(): void
    {
        Mail::fake();
        $use = $this->reserve(Coupon::factory()->create());
        $attempt = PaymentAttempt::findOrFail($use->payment_attempt_id);
        $data = get_object_vars($this->evidence($attempt, 'paid'));
        $data['paymentIntentId'] = 'pi_coupon_fixture';
        $data['chargeId'] = 'ch_coupon_fixture';
        $result = new VerifiedPaymentResult(...$data);
        app(PaymentResultApplier::class)->apply($attempt, $result);
        app(PaymentResultApplier::class)->apply($attempt, $result);
        $this->assertSame(CouponUseStatus::Consumed, $use->fresh()->status);
        $data['refunds'] = [['id' => 're_coupon_fixture', 'amount' => $attempt->amount_cents, 'status' => 'succeeded', 'created' => now()->timestamp]];
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult(...$data));
        $this->assertSame(CouponUseStatus::Consumed, $use->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseCount('order_notifications', 1);
        $this->assertDatabaseCount('coupon_use_histories', 2);
    }

    public function test_common_expiration_releases_once_and_late_payment_preserves_money_with_incident(): void
    {
        Mail::fake();
        $use = $this->reserve(Coupon::factory()->create());
        $attempt = PaymentAttempt::findOrFail($use->payment_attempt_id);
        app(PaymentResultApplier::class)->apply($attempt, $this->evidence($attempt));
        app(PaymentResultApplier::class)->apply($attempt, $this->evidence($attempt));
        $this->assertSame(CouponUseStatus::Free, $use->fresh()->status);
        $data = get_object_vars($this->evidence($attempt, 'paid'));
        $data['paymentIntentId'] = 'pi_late_coupon';
        $data['chargeId'] = 'ch_late_coupon';
        app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult(...$data));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(CouponUseStatus::Free, $use->fresh()->status);
        $this->assertDatabaseHas('financial_incidents', ['kind' => 'coupon_reservation_conflict']);
    }
}
