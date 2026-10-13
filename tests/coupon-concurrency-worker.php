<?php

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Data\VerifiedPaymentResult;
use App\Enums\PriceTier;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CouponReservationService;
use App\Services\InstallerInvitationService;
use App\Services\PaymentResultApplier;
use App\Services\PricingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\MySqlTestEnvironment;

try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    MySqlTestEnvironment::configure();
} catch (Throwable $exception) {
    echo json_encode(['result' => 'bootstrap_error', 'class' => $exception::class, 'code' => $exception->getCode(), 'pid' => getmypid()]);
    exit(1);
}

[$script, $directory, $slot, $mode, $couponId, $attemptId, $otherCouponId, $submissionKey] = array_pad($argv, 8, '0');
$wait = function (string $file): void {
    $deadline = microtime(true) + 20;
    while (! is_file($file)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }
        usleep(10000);
    }
};
$attempt = PaymentAttempt::findOrFail((int) $attemptId);
$order = $attempt->order;
$contact = new ContactData($order->contact_name, $order->contact_email, $order->phone_original, $order->phone_canonical);
$tries = 0;
try {
    if (in_array($mode, ['checkout', 'snapshot-checkout', 'coupon-checkout', 'snapshot-coupon-checkout'], true)) {
        $coupon = Coupon::findOrFail((int) $couponId);
        $lines = [new CartLineData($coupon->products->sole()->id, 1)];
        $withCoupon = in_array($mode, ['coupon-checkout', 'snapshot-coupon-checkout'], true);
        $withSnapshot = in_array($mode, ['snapshot-checkout', 'snapshot-coupon-checkout'], true);
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public, $withCoupon ? $coupon : null);
        if ($withSnapshot) {
            DB::beginTransaction();
            PaymentAttempt::query()->count();
        }
        touch($directory.'/ready-'.$slot);
        $wait($directory.'/go');
        $attempt = app(CheckoutService::class)->start($lines, $contact, null, $quote->fingerprint, $submissionKey, $withCoupon ? $coupon->code : null);
        if ($withSnapshot) {
            DB::commit();
        }
        $use = null;
    } elseif ($mode === 'deadlock') {
        $use = DB::transaction(function () use (&$tries, $directory, $slot, $couponId, $otherCouponId, $wait, $contact, $order, $attempt) {
            $tries++;
            Coupon::query()->whereKey((int) $couponId)->lockForUpdate()->firstOrFail();
            if ($tries === 1) {
                touch($directory.'/ready-'.$slot);
                $wait($directory.'/go');
            }
            Coupon::query()->whereKey((int) $otherCouponId)->lockForUpdate()->firstOrFail();

            return app(CouponReservationService::class)->reserve(Coupon::findOrFail((int) $couponId), $contact, $order, $attempt);
        }, 3);
    } elseif (in_array($mode, ['snapshot', 'snapshot-payment'], true)) {
        DB::beginTransaction();
        CouponUse::query()->count();
        touch($directory.'/ready-'.$slot);
        $wait($directory.'/go');
        try {
            if ($mode === 'snapshot-payment') {
                app(PaymentResultApplier::class)->apply($attempt, new VerifiedPaymentResult($attempt->reference, $order->reference,
                    'stripe_api', 'evt_snapshot', 'acct_fixture', false, $attempt->currency, $attempt->amount_cents,
                    'paid', $attempt->stripe_session_id, 'pi_snapshot', 'ch_snapshot'));
                $use = null;
            } else {
                $use = app(CouponReservationService::class)->reserve(Coupon::findOrFail((int) $couponId), $contact, $order, $attempt);
            }
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    } else {
        touch($directory.'/ready-'.$slot);
        $wait($directory.'/go');
        if ($mode === 'suspend') {
            DB::listen(function ($query) use ($directory, $wait): void {
                if (str_starts_with($query->sql, 'select `id` from `payment_attempts`')) {
                    touch($directory.'/pending-captured');
                    $wait($directory.'/payment-applied');
                }
            });
            app(InstallerInvitationService::class)->suspend(User::findOrFail($order->user_id), User::where('role', 'administrator')->firstOrFail());
            $use = null;
        } elseif ($mode === 'reserve') {
            $use = DB::transaction(fn () => app(CouponReservationService::class)->reserve(Coupon::findOrFail((int) $couponId), $contact, $order, $attempt), 3);
        } else {
            if ($mode === 'pay-during-suspension') {
                $wait($directory.'/pending-captured');
            }
            $paid = in_array($mode, ['consume', 'pay-during-suspension'], true);
            $data = new VerifiedPaymentResult($attempt->reference, $order->reference, 'stripe_api', 'evt_race',
                'acct_fixture', false, $attempt->currency, $attempt->amount_cents,
                $paid ? 'paid' : 'expired', $attempt->stripe_session_id,
                $paid ? 'pi_race' : null, $paid ? 'ch_race' : null);
            app(PaymentResultApplier::class)->apply($attempt, $data);
            if ($mode === 'pay-during-suspension') {
                touch($directory.'/payment-applied');
            }
            $use = null;
        }
    }
    echo json_encode(['result' => 'success', 'use_id' => $use?->id, 'attempt_id' => $attempt->id, 'tries' => $tries, 'pid' => getmypid()]);
} catch (ValidationException) {
    echo json_encode(['result' => 'rejected', 'attempt_id' => $attempt->id, 'pid' => getmypid()]);
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'class' => $exception::class, 'code' => $exception->getCode(), 'pid' => getmypid()]);
    exit(1);
}
