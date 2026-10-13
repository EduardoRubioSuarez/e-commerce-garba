<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\AdministratorLoginCode;
use App\Mail\InstallerInvitationMail;
use App\Mail\PasswordResetMail;
use App\Mail\PurchaseConfirmed;
use App\Mail\ReadyForPickup;
use App\Models\AdminLoginChallenge;
use App\Models\FinancialIncident;
use App\Models\InstallerInvitation;
use App\Models\NotificationAttempt;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

class NotificationDispatcher
{
    public function attempt(OrderNotification $notification): void
    {
        $deadline = config('services.stripe.command_deadline');
        if ($deadline !== null && (float) $deadline - hrtime(true) / 1e9 < 6) {
            return;
        }
        if (DB::transactionLevel() !== 0) {
            DB::afterCommit(fn () => $this->attempt($notification));

            return;
        }
        $claim = DB::transaction(function () use ($notification): ?array {
            $order = Order::query()->whereKey($notification->order_id)->lockForUpdate()->first();
            $user = $order === null ? User::query()->whereKey($notification->user_id)->lockForUpdate()->first() : null;
            $current = OrderNotification::query()->whereKey($notification->id)->lockForUpdate()->firstOrFail();
            if (($order === null && $user === null) || ($order !== null && $order->status !== OrderStatus::Paid)) {
                return null;
            }
            if (in_array($current->status, [NotificationStatus::Accepted, NotificationStatus::Cancelled, NotificationStatus::Uncertain], true)) {
                return null;
            }
            if ($order === null && ! $this->accessIsCurrent($current, $user)) {
                $this->cancelAccess($current);

                return null;
            }
            if ($current->status === NotificationStatus::Claimed) {
                if ($current->lease_expires_at === null || $current->lease_expires_at->isFuture()) {
                    return null;
                }
                $abandoned = NotificationAttempt::query()->where('claim_token', $current->claim_token)->lockForUpdate()->first();
                if ($abandoned !== null && $abandoned->status === 'sending') {
                    $abandoned->status = 'uncertain';
                    $abandoned->error = 'lease_expired_after_send_started';
                    $abandoned->finished_at = now();
                    $this->save($abandoned);
                    $current->status = NotificationStatus::Uncertain;
                    $current->last_error = 'delivery_outcome_unknown';
                    $current->next_attempt_at = null;
                    $current->claim_token = null;
                    $current->lease_expires_at = null;
                    $this->save($current);

                    return null;
                }
                if ($abandoned !== null) {
                    $abandoned->status = 'failed';
                    $abandoned->error = 'abandoned_before_send';
                    $abandoned->finished_at = now();
                    $this->save($abandoned);
                }
                $current->status = NotificationStatus::Pending;
                $current->claim_token = null;
                $current->lease_expires_at = null;
            } elseif ($current->next_attempt_at !== null && $current->next_attempt_at->isFuture()) {
                return null;
            }
            if ($current->type === 'pickup') {
                $reason = $this->pickupBlockReason($order);
                if ($reason !== null) {
                    $current->blocked_reason = $reason;
                    if ($reason === 'fully_refunded') {
                        $current->status = NotificationStatus::Cancelled;
                        $current->cancelled_at = now();
                        $current->cancellation_reason = $reason;
                        $current->next_attempt_at = null;
                    }
                    $this->save($current);

                    return null;
                }
                $current->blocked_reason = null;
            }
            $token = (string) Str::uuid();
            $current->status = NotificationStatus::Claimed;
            $current->claim_token = $token;
            $current->claimed_at = now();
            $current->lease_expires_at = now()->addSeconds(60);
            $current->last_error = null;
            $this->save($current);
            $attempt = new NotificationAttempt;
            $attempt->forceFill(['order_notification_id' => $current->id, 'order_id' => $order?->id, 'user_id' => $order?->user_id ?? $user?->id,
                'claim_token' => $token, 'status' => 'prepared', 'started_at' => now()]);
            $this->save($attempt);

            return [$current, $order, $attempt];
        }, 3);
        if ($claim === null) {
            return;
        }
        [$current,$order,$attempt] = $claim;
        try {
            $mail = $order === null ? match ($current->type) {
                'installer_invitation' => new InstallerInvitationMail($current->payload['token']),
                'admin_login_code' => new AdministratorLoginCode($current->payload['code']),
                'password_reset' => new PasswordResetMail($current->payload['token'], $current->destination),
                default => throw new RuntimeException('Unsupported access notification.'),
            }
            : ($current->type === 'pickup' ? new ReadyForPickup($order) : new PurchaseConfirmed($order));
            $mail->render();
            if (! app()->environment('testing') && in_array(config('mail.default'), ['array', 'log', 'failover'], true)) {
                throw new RuntimeException('Mail provider not ready.');
            }
        } catch (Throwable) {
            $this->finish($current, $attempt, NotificationStatus::Failed, 'mail_preparation_failed');

            return;
        }
        if ($deadline !== null && (float) $deadline - hrtime(true) / 1e9 < 6) {
            $this->finish($current, $attempt, NotificationStatus::Failed, 'time_budget_exhausted');

            return;
        }
        $canSend = DB::transaction(function () use ($current, $attempt): bool {
            $order = Order::query()->whereKey($current->order_id)->lockForUpdate()->first();
            $user = $order === null ? User::query()->whereKey($current->user_id)->lockForUpdate()->firstOrFail() : null;
            $fresh = OrderNotification::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();
            if ($fresh->claim_token !== $attempt->claim_token || $fresh->status !== NotificationStatus::Claimed) {
                throw new RuntimeException('Notification claim lost.');
            }
            if ($order === null && ! $this->accessIsCurrent($fresh, $user)) {
                $attempt->status = 'failed';
                $attempt->error = 'access_no_longer_current';
                $attempt->finished_at = now();
                $this->save($attempt);
                $this->cancelAccess($fresh);

                return false;
            }
            $reason = $fresh->type === 'pickup' ? $this->pickupBlockReason($order) : null;
            if ($reason !== null) {
                $attempt->status = 'failed';
                $attempt->error = 'ineligible_before_send';
                $attempt->finished_at = now();
                $this->save($attempt);
                $fresh->claim_token = null;
                $fresh->lease_expires_at = null;
                $fresh->blocked_reason = $reason;
                $fresh->status = $reason === 'fully_refunded' ? NotificationStatus::Cancelled : NotificationStatus::Pending;
                if ($reason === 'fully_refunded') {
                    $fresh->cancelled_at = now();
                    $fresh->cancellation_reason = $reason;
                    $fresh->next_attempt_at = null;
                }
                $this->save($fresh);

                return false;
            }
            $attempt->status = 'sending';
            $attempt->sending_at = now();
            $this->save($attempt);

            return true;
        }, 3);
        if (! $canSend) {
            return;
        }
        if (! Mail::isFake()) {
            $transport = Mail::mailer()->getSymfonyTransport();
            if ($transport instanceof SmtpTransport) {
                $remaining = $deadline === null ? 5.0 : max(0.1, (float) $deadline - hrtime(true) / 1e9);
                $transport->getStream()->setTimeout(min(5.0, $remaining));
            }
        }
        try {
            $sent = Mail::to($current->destination)->send($mail);
            if ($sent === null && ! Mail::isFake()) {
                $this->finish($current, $attempt, NotificationStatus::Failed, 'provider_did_not_accept');

                return;
            }
            $messageId = $sent?->getMessageId();
        } catch (Throwable $error) {
            $definitive = $error instanceof TransportExceptionInterface && $error->getCode() >= 400 && $error->getCode() <= 599;
            $this->finish($current, $attempt, $definitive ? NotificationStatus::Failed : NotificationStatus::Uncertain,
                $definitive ? 'provider_rejected' : 'delivery_outcome_unknown');

            return;
        }
        $this->finish($current, $attempt, NotificationStatus::Accepted, null, $messageId);
    }

    private function finish(OrderNotification $notification, NotificationAttempt $attempt, NotificationStatus $status, ?string $error, ?string $messageId = null): void
    {
        DB::transaction(function () use ($notification, $attempt, $status, $error, $messageId): void {
            if ($notification->order_id !== null) {
                Order::query()->whereKey($notification->order_id)->lockForUpdate()->firstOrFail();
            } else {
                User::query()->whereKey($notification->user_id)->lockForUpdate()->firstOrFail();
            }
            $current = OrderNotification::query()->whereKey($notification->id)->lockForUpdate()->firstOrFail();
            $stored = NotificationAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $stored->status = $status->value;
            $stored->finished_at = now();
            $stored->error = $error;
            $stored->provider_message_id = $messageId;
            $this->save($stored);
            if ($current->claim_token !== $attempt->claim_token) {
                return;
            }
            $current->status = $status;
            $current->last_error = $error;
            if ($status === NotificationStatus::Accepted) {
                $current->accepted_at = now();
                $current->payload = null;
            }
            $current->next_attempt_at = $status === NotificationStatus::Failed ? now()->addMinutes(5) : null;
            $current->claim_token = null;
            $current->lease_expires_at = null;
            $this->save($current);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function enqueueAccess(User $user, string $type, string $key, array $payload): OrderNotification
    {
        return DB::transaction(function () use ($user, $type, $key, $payload): OrderNotification {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previous = OrderNotification::query()->whereNull('order_id')->where('user_id', $user->id)->where('type', $type)
                ->whereIn('status', [NotificationStatus::Pending, NotificationStatus::Failed, NotificationStatus::Claimed])->lockForUpdate()->get();
            foreach ($previous as $old) {
                $this->cancelAccess($old);
            }
            $notification = new OrderNotification;
            $notification->forceFill(['user_id' => $user->id, 'order_id' => null, 'type' => $type,
                'notification_key' => $key, 'destination' => $user->email, 'payload' => $payload,
                'status' => NotificationStatus::Pending, 'requested_at' => now(), 'next_attempt_at' => now()]);
            $this->save($notification);
            DB::afterCommit(function () use ($notification): void {
                try {
                    $this->attempt($notification);
                } catch (Throwable) {
                    // The durable access notification remains recoverable.
                }
            });

            return $notification;
        }, 3);
    }

    private function accessIsCurrent(OrderNotification $notification, User $user): bool
    {
        $payload = $notification->payload;
        if (! is_array($payload) || $notification->destination !== $user->email || $user->suspended_at !== null) {
            return false;
        }
        if ($notification->type === 'installer_invitation') {
            $invitation = InstallerInvitation::query()->find($payload['invitation_id'] ?? 0);

            return $user->role === UserRole::Installer && ! $user->isActive()
                && $invitation !== null && $invitation->user_id === $user->id
                && $invitation->used_at === null && $invitation->revoked_at === null
                && $invitation->expires_at->isFuture() && is_string($payload['token'] ?? null)
                && hash_equals($invitation->token_hash, hash('sha256', $payload['token']));
        }
        if ($notification->type === 'admin_login_code') {
            $challenge = AdminLoginChallenge::query()->find($payload['challenge_id'] ?? '');

            return $user->isActive() && $user->isAdministrator() && $challenge !== null
                && $challenge->user_id === $user->id && $challenge->auth_version === $user->auth_version
                && $challenge->used_at === null && $challenge->revoked_at === null && $challenge->attempts < 5
                && $challenge->expires_at->isFuture() && is_string($payload['code'] ?? null)
                && Hash::check($payload['code'], $challenge->code_hash);
        }
        if ($notification->type === 'password_reset') {
            return $user->isActive() && is_string($payload['token'] ?? null)
                && Password::tokenExists($user, $payload['token']);
        }

        return false;
    }

    private function cancelAccess(OrderNotification $notification): void
    {
        $notification->forceFill(['status' => NotificationStatus::Cancelled, 'cancelled_at' => now(),
            'cancellation_reason' => 'access_no_longer_current', 'next_attempt_at' => null,
            'claim_token' => null, 'lease_expires_at' => null, 'payload' => null]);
        $this->save($notification);
    }

    public function pickupBlockReason(Order $order): ?string
    {
        if ($order->status !== OrderStatus::Paid) {
            return 'not_paid';
        }
        $paymentIds = Payment::query()->where('order_id', $order->id)->pluck('id');
        $gross = (int) Payment::query()->whereIn('id', $paymentIds)->sum('amount_cents');
        $refunded = (int) Refund::query()->whereIn('payment_id', $paymentIds)->where('status', 'succeeded')->sum('amount_cents');
        if ($gross > 0 && $refunded >= $gross) {
            return 'fully_refunded';
        }
        if (FinancialIncident::query()->where('order_id', $order->id)->whereNull('resolved_at')->exists()) {
            return 'financial_incident';
        }

        return null;
    }

    private function save(Model $model): void
    {
        if (! $model->save()) {
            throw new RuntimeException('Notification could not be persisted.');
        }
    }
}
