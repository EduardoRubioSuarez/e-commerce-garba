<?php

use App\Data\CartLineData;
use App\Data\ContactData;
use App\Data\VerifiedPaymentResult;
use App\Enums\PriceTier;
use App\Http\Controllers\Admin\OrderNotificationController;
use App\Models\OrderNotification;
use App\Models\PaymentAttempt;
use App\Models\StripeEvent;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\NotificationDispatcher;
use App\Services\PaymentReconciler;
use App\Services\PaymentResultApplier;
use App\Services\PaymentResultResolver;
use App\Services\PricingService;
use App\Services\StripeGateway;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\MySqlTestEnvironment;

try {
    require dirname(__DIR__).'/vendor/autoload.php';
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    MySqlTestEnvironment::configure();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'services.stripe.secret' => 'sk_test_fixture',
        'services.stripe.account_id' => 'acct_fixture', 'services.stripe.livemode' => false, 'store.recovery.cache_store' => 'array']);
    [$script, $directory, $slot, $mode, $attemptId, $targetId, $suffix] = $argv;
    $attempt = PaymentAttempt::findOrFail((int) $attemptId);
    $order = $attempt->order;
    $httpInTransaction = 0;
    $mailInTransaction = 0;
    $sent = 0;
    $metadata = ['attempt_reference' => $attempt->reference, 'order_reference' => $order->reference];
    $session = ['id' => 'cs_test_fixture', 'object' => 'checkout.session', 'mode' => 'payment', 'status' => $mode === 'resume' ? 'open' : 'complete',
        'payment_status' => $mode === 'resume' ? 'unpaid' : 'paid', 'amount_total' => 150800, 'currency' => 'mxn', 'livemode' => false,
        'expires_at' => now()->addMinutes(30)->timestamp, 'payment_intent' => $mode === 'resume' ? null : 'pi_fixture',
        'url' => $mode === 'resume' ? 'https://checkout.stripe.com/c/pay/cs_test_fixture' : null, 'metadata' => $metadata];
    $intent = ['id' => 'pi_fixture', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 150800, 'amount_received' => 150800,
        'currency' => 'mxn', 'livemode' => false, 'metadata' => $metadata, 'latest_charge' => 'ch_fixture'];
    $charge = ['id' => 'ch_fixture', 'object' => 'charge', 'payment_intent' => 'pi_fixture', 'amount' => 150800,
        'currency' => 'mxn', 'livemode' => false, 'paid' => true, 'captured' => true, 'status' => 'succeeded', 'created' => now()->timestamp];
    Http::preventStrayRequests();
    Http::fake(function ($request) use (&$httpInTransaction, $session, $intent, $charge, $mode, $attempt) {
        if (DB::transactionLevel() !== 0) {
            $httpInTransaction++;
            throw new LogicException('HTTP inside financial transaction.');
        }
        if ($mode === 'resume' && $request->method() === 'POST'
            && ($request->header('Idempotency-Key')[0] !== $attempt->idempotency_key || $request->data() !== $attempt->checkout_parameters)) {
            throw new LogicException('Resumption changed immutable request.');
        }

        return Http::response(match (true) {
            str_ends_with($request->url(), '/account') => ['id' => 'acct_fixture'],
            str_contains($request->url(), '/checkout/sessions') => $session,
            str_contains($request->url(), '/payment_intents/') => $intent,
            str_contains($request->url(), '/charges/') => $charge,
            default => throw new RuntimeException('Unexpected HTTP fixture'),
        });
    });
    Event::listen(MessageSending::class, function () use (&$sent, &$mailInTransaction): void {
        if (DB::transactionLevel() !== 0) {
            $mailInTransaction++;
            throw new LogicException('Mail inside financial transaction.');
        }
        $sent++;
    });
    $wait = function (string $file): void {
        $deadline = microtime(true) + 25;
        while (! is_file($file)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Financial barrier timeout');
            }
            usleep(10000);
        }
    };
    if ($mode === 'snapshot-checkout') {
        $lines = [new CartLineData((int) $targetId, 1)];
        $quote = app(PricingService::class)->quote($lines, PriceTier::Public);
        DB::beginTransaction();
        PaymentAttempt::query()->count();
    }
    touch($directory.'/ready-'.$slot);
    $wait($directory.'/go');
    touch($directory.'/entered-'.$slot);
    if (in_array($mode, ['apply', 'expire'], true)) {
        $event = (int) $targetId === 0 ? null : StripeEvent::findOrFail((int) $targetId);
        $result = new VerifiedPaymentResult($attempt->reference, $order->reference, 'fixture', $event?->stripe_event_id ?? 'evt_'.$slot,
            'acct_fixture', false, 'MXN', 150800, $mode === 'expire' ? 'expired' : 'paid', 'cs_test_fixture',
            $mode === 'expire' ? null : 'pi_'.$suffix, $mode === 'expire' ? null : 'ch_'.$suffix, now()->timestamp);
        app(PaymentResultApplier::class)->apply($attempt, $result, $event);
    } elseif ($mode === 'webhook') {
        $event = StripeEvent::findOrFail((int) $targetId);
        $result = app(PaymentResultResolver::class)->resolve($event);
        app(PaymentResultApplier::class)->apply($attempt, $result, $event);
    } elseif ($mode === 'cron') {
        app(PaymentReconciler::class)->run(25);
    } elseif ($mode === 'pickup') {
        request()->setUserResolver(fn () => User::findOrFail((int) $targetId));
        app(OrderNotificationController::class)->store($order);
    } elseif ($mode === 'notifications-cron') {
        app(PaymentReconciler::class)->retryNotifications(25);
    } elseif ($mode === 'dispatch') {
        app(NotificationDispatcher::class)->attempt(OrderNotification::findOrFail((int) $targetId));
    } elseif ($mode === 'snapshot-checkout') {
        $attempt = app(CheckoutService::class)->start($lines, new ContactData($order->contact_name, $order->contact_email,
            $order->phone_original, $order->phone_canonical), null, $quote->fingerprint, $order->reference);
        DB::commit();
    } elseif ($mode === 'resume') {
        app(StripeGateway::class)->createCheckout($attempt);
    } else {
        throw new RuntimeException('Unsupported financial race mode');
    }
    echo json_encode(['result' => 'ok', 'pid' => getmypid(), 'attempt_id' => $attempt->id, 'sent' => $sent,
        'http_inside_transaction' => $httpInTransaction, 'mail_inside_transaction' => $mailInTransaction]);
} catch (Throwable $exception) {
    echo json_encode(['result' => 'worker_error', 'class' => $exception::class, 'code' => $exception->getCode(), 'pid' => getmypid()]);
    exit(1);
}
