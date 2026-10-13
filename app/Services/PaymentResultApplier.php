<?php

namespace App\Services;

use App\Data\VerifiedPaymentResult;
use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\FinancialIncident;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\StripeEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentResultApplier
{
    public function apply(PaymentAttempt $attempt, VerifiedPaymentResult $result, ?StripeEvent $event = null): void
    {
        DB::transaction(function () use ($attempt, $result, $event): void {
            $ownerId = $attempt->order->user_id;
            $owner = $ownerId === null ? null : User::query()->whereKey($ownerId)->lockForUpdate()->first();
            $coupons = app(CouponReservationService::class);
            $couponUse = $coupons->lockForAttempt($attempt);
            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $current = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $problems = $result->problems;
            if ($result->attemptReference !== $current->reference || $result->orderReference !== $order->reference) {
                $problems[] = 'reference_mismatch';
            }
            if ($result->accountId !== config('services.stripe.account_id')) {
                $problems[] = 'account_mismatch';
            }
            if ($result->livemode !== (bool) config('services.stripe.livemode')) {
                $problems[] = 'mode_mismatch';
            }
            if ($result->currency !== $current->currency) {
                $problems[] = 'currency_mismatch';
            }
            if ($result->amountCents !== $current->amount_cents) {
                $problems[] = 'amount_mismatch';
            }
            if ($result->sessionId !== null && $current->stripe_session_id !== null && $result->sessionId !== $current->stripe_session_id) {
                $problems[] = 'session_mismatch';
            }
            $payment = null;
            if ($result->state === 'paid' && $result->chargeId !== null && $result->paymentIntentId !== null) {
                $payment = Payment::query()->where('stripe_charge_id', $result->chargeId)->lockForUpdate()->first();
                if ($payment === null) {
                    if (Payment::query()->where('stripe_payment_intent_id', $result->paymentIntentId)->exists()) {
                        $problems[] = 'intent_charge_conflict';
                    } else {
                        $payment = new Payment;
                        $payment->forceFill(['order_id' => $order->id, 'payment_attempt_id' => $current->id, 'stripe_charge_id' => $result->chargeId,
                            'stripe_payment_intent_id' => $result->paymentIntentId, 'amount_cents' => $result->amountCents, 'currency' => $result->currency,
                            'account_id' => $result->accountId, 'livemode' => $result->livemode, 'source' => $result->source, 'evidence_id' => $result->evidenceId,
                            'paid_at' => CarbonImmutable::createFromTimestampUTC($result->occurredAt ?? now()->timestamp)]);
                        $this->save($payment);
                    }
                } elseif ($payment->order_id !== $order->id || $payment->payment_attempt_id !== $current->id
                    || $payment->amount_cents !== $result->amountCents || $payment->currency !== $result->currency) {
                    $problems[] = 'charge_identity_conflict';
                }
                if (Payment::query()->where('order_id', $order->id)->count() > 1) {
                    $this->incident($order, $current, 'duplicate_charge', $result->chargeId, 'Additional charge preserved.');
                }
                if ($payment !== null && $problems === []) {
                    if ($couponUse !== null) {
                        try {
                            $coupons->consume($couponUse, $payment);
                        } catch (ValidationException) {
                            $this->incident($order, $current, 'coupon_reservation_conflict', $result->chargeId, 'Payment preserved without consuming a released or foreign coupon right.');
                        }
                    }
                    $wasPaid = $order->status === OrderStatus::Paid;
                    if (! $wasPaid && $owner?->suspended_at !== null) {
                        $this->incident($order, $current, 'payment_after_suspension', $result->chargeId, 'Payment preserved after account revocation.');
                    }
                    if ($order->status === OrderStatus::ClosedUnpaid) {
                        $this->incident($order, $current, 'late_payment', $result->chargeId, 'Payment received after closure.');
                    }
                    $current->status = PaymentAttemptStatus::Paid;
                    $current->stripe_payment_intent_id = $result->paymentIntentId;
                    $current->stripe_session_id ??= $result->sessionId;
                    $this->save($current);
                    $order->status = OrderStatus::Paid;
                    $this->save($order);
                    if (! $wasPaid && ! OrderNotification::query()->where('notification_key', 'purchase:'.$order->reference)->exists()) {
                        $notification = new OrderNotification;
                        $notification->forceFill(['order_id' => $order->id, 'notification_key' => 'purchase:'.$order->reference,
                            'type' => 'purchase', 'destination' => $order->contact_email, 'status' => NotificationStatus::Pending, 'requested_at' => now(), 'next_attempt_at' => now()]);
                        $this->save($notification);
                        DB::afterCommit(function () use ($notification): void {
                            try {
                                app(NotificationDispatcher::class)->attempt($notification);
                            } catch (\Throwable) { /* Durable outbox remains recoverable. */
                            }
                        });
                    }
                }
            } elseif ($result->state === 'failed' && $result->source === 'stripe_create_rejection' && $problems === []
                && $current->status === PaymentAttemptStatus::Creating && $current->stripe_session_id === null
                && $order->status !== OrderStatus::Paid && ! Payment::query()->where('order_id', $order->id)->exists()) {
                $current->status = PaymentAttemptStatus::Failed;
                $current->last_error = 'definitive_creation_rejection';
                $this->save($current);
                if ($couponUse !== null && $couponUse->order_id === $order->id && $couponUse->payment_attempt_id === $current->id) {
                    $coupons->release($couponUse, $result);
                }
                if (! PaymentAttempt::query()->where('order_id', $order->id)->whereIn('status', [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Open, PaymentAttemptStatus::Unknown, PaymentAttemptStatus::Paid])->exists()) {
                    $order->status = OrderStatus::ClosedUnpaid;
                    $this->save($order);
                }
            } elseif ($result->state === 'expired' && $problems === []) {
                if ($current->status === PaymentAttemptStatus::Paid || $order->status === OrderStatus::Paid
                    || Payment::query()->where('order_id', $order->id)->exists()) {
                    $this->incident($order, $current, 'expiration_after_payment', $result->sessionId ?? $result->evidenceId, 'Expiration contradicts recorded money.');
                } else {
                    if ($couponUse !== null) {
                        if ($couponUse->order_id === $order->id && $couponUse->payment_attempt_id === $current->id) {
                            $coupons->release($couponUse, $result);
                        } else {
                            $this->incident($order, $current, 'coupon_release_conflict', $result->evidenceId, 'Old expiration cannot release a reused coupon right.');
                        }
                    }
                    $current->stripe_session_id ??= $result->sessionId;
                    $current->status = PaymentAttemptStatus::Expired;
                    $this->save($current);
                    if (! PaymentAttempt::query()->where('order_id', $order->id)->whereIn('status', [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Open, PaymentAttemptStatus::Unknown, PaymentAttemptStatus::Paid])->exists()) {
                        $order->status = OrderStatus::ClosedUnpaid;
                        $this->save($order);
                    }
                }
            }
            foreach (array_unique($problems) as $problem) {
                $this->incident($order, $current, $problem, $result->chargeId ?? $result->sessionId ?? $result->evidenceId, 'Stripe evidence requires review.');
            }
            if ($payment !== null && $payment->order_id === $order->id && $problems === []) {
                foreach ($result->refunds as $data) {
                    $refund = Refund::query()->where('stripe_refund_id', $data['id'])->lockForUpdate()->first();
                    if ($refund !== null && ($refund->payment_id !== $payment->id || $refund->amount_cents !== $data['amount'])) {
                        $this->incident($order, $current, 'refund_conflict', $data['id'], 'Refund identity requires review.');

                        continue;
                    }
                    $refund ??= new Refund;
                    $refund->forceFill(['payment_id' => $payment->id, 'stripe_refund_id' => $data['id'], 'amount_cents' => $data['amount'], 'currency' => $result->currency]);
                    if (! $refund->exists || $refund->status !== 'succeeded') {
                        $refund->status = $data['status'];
                        $refund->confirmed_at = $data['status'] === 'succeeded' ? CarbonImmutable::createFromTimestampUTC($data['created']) : null;
                    }
                    $this->save($refund);
                }
                if ($result->disputeId !== null) {
                    $incident = $this->incident($order, $current, 'dispute', $result->disputeId, 'Dispute '.$result->disputeStatus);
                    if (in_array($result->disputeStatus, ['won', 'warning_closed'], true)) {
                        $incident->resolved_at = now();
                        $this->save($incident);
                    }
                }
            }
            if ($event !== null) {
                $stored = StripeEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                $stored->status = 'processed';
                $stored->processed_at = now();
                $stored->last_error = null;
                $this->save($stored);
            }
        }, 3);
    }

    private function incident(Order $order, PaymentAttempt $attempt, string $kind, ?string $remoteId, string $reason): FinancialIncident
    {
        $key = hash('sha256', $order->reference.'|'.$kind.'|'.$remoteId);
        $incident = FinancialIncident::query()->where('incident_key', $key)->first();
        if ($incident === null) {
            $incident = new FinancialIncident;
            $incident->forceFill(['order_id' => $order->id, 'payment_attempt_id' => $attempt->id, 'incident_key' => $key, 'kind' => $kind, 'remote_id' => $remoteId, 'reason' => $reason]);
            $this->save($incident);
        }

        return $incident;
    }

    private function save(Model $model): void
    {
        if (! $model->save()) {
            throw new RuntimeException('Financial result could not be persisted.');
        }
    }
}
