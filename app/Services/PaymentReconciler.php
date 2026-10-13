<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\OrderNotification;
use App\Models\PaymentAttempt;
use App\Models\StripeEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PaymentReconciler
{
    public function __construct(private StripeGateway $gateway, private PaymentResultResolver $resolver,
        private PaymentResultApplier $applier, private NotificationDispatcher $dispatcher) {}

    public function recoverAttempt(PaymentAttempt $attempt): void
    {
        $this->gateway->assertOutsideTransaction();
        $session = $attempt->stripe_session_id !== null ? $this->gateway->retrieveCheckout($attempt->stripe_session_id) : $this->gateway->createCheckout($attempt);
        if ($session->paymentStatus === 'paid' || $session->status === 'expired') {
            $evidence = new StripeEvent;
            $evidence->forceFill(['stripe_event_id' => 'reconcile:'.$attempt->reference,
                'type' => $session->status === 'expired' ? 'checkout.session.expired' : 'checkout.session.completed',
                'payload' => ['api_version' => config('services.stripe.api_version'), 'livemode' => (bool) config('services.stripe.livemode'),
                    'created' => now()->timestamp, 'data' => ['object' => ['id' => $session->id]]]]);
            $this->applier->apply($attempt, $this->resolver->resolve($evidence));
        } else {
            $this->gateway->validateSession($attempt, $session);
            $this->gateway->remember($attempt, $session);
        }
    }

    public function run(int $limit): int
    {
        $this->validateLimit($limit);
        $this->gateway->assertOutsideTransaction();
        $deadline = hrtime(true) / 1e9 + (float) config('store.recovery.budget_seconds', 40);
        $previousDeadline = config('services.stripe.command_deadline');
        config(['services.stripe.command_deadline' => $deadline]);
        $metrics = $this->start('payments');
        $completed = 0;
        $errors = 0;
        $visited = 0;
        try {
            $events = StripeEvent::query()->where('status', 'pending')->orderByRaw('last_checked_at IS NOT NULL')
                ->orderBy('last_checked_at')->orderBy('id')->limit($limit)->get();
            $attempts = PaymentAttempt::query()->whereIn('status', [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Open, PaymentAttemptStatus::Unknown])
                ->orderByRaw('last_checked_at IS NOT NULL')->orderBy('last_checked_at')->orderBy('id')->limit($limit)->get();
            $work = $events->map(fn (StripeEvent $event): array => ['model' => $event, 'event' => true])
                ->concat($attempts->map(fn (PaymentAttempt $attempt): array => ['model' => $attempt, 'event' => false]))
                ->sort(function (array $left, array $right): int {
                    $a = $left['model'];
                    $b = $right['model'];

                    return [$a->last_checked_at?->timestamp ?? 0, $a->created_at->timestamp, $left['event'] ? 0 : 1, $a->id]
                        <=> [$b->last_checked_at?->timestamp ?? 0, $b->created_at->timestamp, $right['event'] ? 0 : 1, $b->id];
                })->take($limit);
            foreach ($work as $item) {
                if (hrtime(true) / 1e9 >= $deadline) {
                    break;
                }
                $visited++;
                $model = $item['model'];
                $model->last_checked_at = now();
                if (! $model->save()) {
                    $errors++;

                    continue;
                }
                try {
                    if ($item['event']) {
                        if (! StripeEvent::supports($model->type)) {
                            $model->status = 'ignored';
                            $model->processed_at = now();
                            if (! $model->save()) {
                                throw new \RuntimeException('Event could not be persisted.');
                            }
                        } else {
                            $result = $this->resolver->resolve($model);
                            $attempt = PaymentAttempt::query()->where('reference', $result->attemptReference)->firstOrFail();
                            $this->applier->apply($attempt, $result, $model);
                        }
                    } else {
                        $this->recoverAttempt($model);
                    }
                    $completed++;
                } catch (Throwable) {
                    $model->refresh();
                    $model->last_error = 'recovery_pending';
                    $model->save();
                    $errors++;
                }
            }
            $metrics['unknown_over_30_minutes'] = PaymentAttempt::query()->where('status', PaymentAttemptStatus::Unknown)
                ->where('first_sent_at', '<=', now()->subMinutes(30))->count();
            if ($metrics['unknown_over_30_minutes'] > 0) {
                Log::warning('store.payment_unknown_alert', ['count' => $metrics['unknown_over_30_minutes']]);
            }
        } finally {
            config(['services.stripe.command_deadline' => $previousDeadline]);
            $this->finish('payments', $metrics, $visited, $completed, $errors);
        }

        return $completed;
    }

    public function retryNotifications(int $limit): int
    {
        $this->validateLimit($limit);
        $this->gateway->assertOutsideTransaction();
        $deadline = hrtime(true) / 1e9 + (float) config('store.recovery.budget_seconds', 40);
        $previousDeadline = config('services.stripe.command_deadline');
        config(['services.stripe.command_deadline' => $deadline]);
        $metrics = $this->start('notifications');
        $completed = 0;
        $visited = 0;
        $errors = 0;
        $notifications = OrderNotification::query()->where(function ($query): void {
            $query->where(function ($ready): void {
                $ready->whereIn('status', [NotificationStatus::Pending, NotificationStatus::Failed])
                    ->where(function ($due): void {
                        $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                    });
            })->orWhere(function ($claim): void {
                $claim->where('status', NotificationStatus::Claimed)->where('lease_expires_at', '<=', now());
            });
        })->orderBy('updated_at')->orderBy('id')->limit($limit)->get();
        try {
            foreach ($notifications as $notification) {
                if (hrtime(true) / 1e9 >= $deadline) {
                    break;
                }
                $visited++;
                try {
                    $this->dispatcher->attempt($notification);
                    if ($notification->fresh()->status === NotificationStatus::Accepted) {
                        $completed++;
                    } elseif (in_array($notification->fresh()->status, [NotificationStatus::Failed, NotificationStatus::Uncertain], true)) {
                        $errors++;
                    }
                } catch (Throwable) {
                    $errors++;
                }
            }
        } finally {
            config(['services.stripe.command_deadline' => $previousDeadline]);
            $this->finish('notifications', $metrics, $visited, $completed, $errors);
        }

        return $completed;
    }

    private function validateLimit(int $limit): void
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Batch limit must be between 1 and 100.');
        }
    }

    /** @return array<string,mixed> */
    private function start(string $operation): array
    {
        $cache = Cache::store(config('store.recovery.cache_store', 'database'));
        $metrics = $cache->get('store:operations:'.$operation, []);
        $metrics['first_started_at'] ??= now()->toIso8601String();
        $metrics['started_at'] = now()->toIso8601String();
        $metrics['finished_at'] = null;
        $last = $metrics['last_success_at'] ?? $metrics['first_started_at'];
        if (CarbonImmutable::parse($last)->lte(now()->subMinutes(45))) {
            Log::warning('store.cron_stale_alert', ['operation' => $operation]);
            $metrics['stale_alert_at'] = now()->toIso8601String();
        }
        $cache->forever('store:operations:'.$operation, $metrics);

        return $metrics;
    }

    /** @param array<string,mixed> $metrics */
    private function finish(string $operation, array $metrics, int $visited, int $completed, int $errors): void
    {
        $metrics['finished_at'] = now()->toIso8601String();
        $metrics['visited'] = $visited;
        $metrics['completed'] = $completed;
        $metrics['errors'] = $errors;
        $metrics['last_error'] = $errors > 0 ? 'work_pending' : null;
        if ($errors === 0) {
            $metrics['last_success_at'] = now()->toIso8601String();
        }
        Cache::store(config('store.recovery.cache_store', 'database'))->forever('store:operations:'.$operation, $metrics);
        Log::info('store.recovery_finished', ['operation' => $operation, 'visited' => $visited, 'completed' => $completed, 'errors' => $errors]);
    }
}
