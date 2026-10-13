<?php

namespace App\Services;

use App\Enums\PaymentAttemptStatus;
use App\Enums\UserRole;
use App\Exceptions\DefinitiveStripeFailure;
use App\Models\AdminLoginChallenge;
use App\Models\AuditEntry;
use App\Models\FinancialIncident;
use App\Models\InstallerInvitation;
use App\Models\NotificationAttempt;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\StripeEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InstallerInvitationService
{
    public function invite(User $installer, User $administrator): void
    {
        $token = bin2hex(random_bytes(32));
        $hours = (int) config('store.access.invitation_hours', 48);
        if ($hours < 1 || $hours > 168) {
            throw new RuntimeException('Invalid invitation lifetime.');
        }
        DB::transaction(function () use ($installer, $administrator, $token, $hours): void {
            $admin = User::query()->whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->isActive() && $admin->isAdministrator(), 403);
            $current = User::query()->whereKey($installer->id)->lockForUpdate()->firstOrFail();
            if ($current->role !== UserRole::Installer || $current->isActive() || $current->suspended_at !== null) {
                throw ValidationException::withMessages(['installer' => 'Esta cuenta no puede recibir una invitación de activación.']);
            }
            InstallerInvitation::query()->where('user_id', $current->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $invitation = new InstallerInvitation;
            $invitation->forceFill(['user_id' => $current->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours($hours)]);
            if (! $invitation->save()) {
                throw new RuntimeException('Invitation persistence failed.');
            }
            $this->audit($admin, $current, 'installer.invited');
            app(NotificationDispatcher::class)->enqueueAccess($current, 'installer_invitation', 'invitation:'.$invitation->id,
                ['invitation_id' => $invitation->id, 'token' => $token]);
        }, 3);
    }

    public function activate(string $token, string $password): User
    {
        Validator::make(['token' => $token, 'password' => $password], [
            'token' => ['required', 'regex:/\A[a-f0-9]{64}\z/'], 'password' => ['required', 'string', 'min:12', 'max:72'],
        ])->validate();

        return DB::transaction(function () use ($token, $password): User {
            $known = InstallerInvitation::query()->where('token_hash', hash('sha256', $token))->first();
            if ($known === null) {
                throw ValidationException::withMessages(['token' => 'La invitación no está disponible. Solicita una nueva.']);
            }
            $user = User::query()->whereKey($known->user_id)->lockForUpdate()->firstOrFail();
            $invitation = InstallerInvitation::query()->whereKey($known->id)->lockForUpdate()->firstOrFail();
            if ($invitation->used_at !== null || $invitation->revoked_at !== null || ! $invitation->expires_at->isFuture()
                || $user->role !== UserRole::Installer || $user->isActive() || $user->suspended_at !== null) {
                throw ValidationException::withMessages(['token' => 'La invitación no está disponible. Solicita una nueva.']);
            }
            $user->forceFill(['password' => $password, 'is_active' => true, 'activated_at' => now(), 'remember_token' => null]);
            $invitation->used_at = now();
            if (! $user->save() || ! $invitation->save()) {
                throw new RuntimeException('Activation persistence failed.');
            }
            $this->audit($user, $user, 'installer.activated');

            return $user;
        }, 3);
    }

    public function suspend(User $installer, User $administrator): void
    {
        DB::transaction(function () use ($installer, $administrator): void {
            $admin = User::query()->whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->isActive() && $admin->isAdministrator(), 403);
            $pending = PaymentAttempt::query()->whereIn('order_id', Order::query()->where('user_id', $installer->id)->select('id'))
                ->whereIn('status', [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Open, PaymentAttemptStatus::Unknown])->pluck('id')->all();
            $current = User::query()->whereKey($installer->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->role === UserRole::Installer, 403);
            if ($current->suspended_at === null) {
                $current->forceFill(['is_active' => false, 'suspended_at' => now(), 'remember_token' => null, 'auth_version' => $current->auth_version + 1]);
                if (! $current->save()) {
                    throw new RuntimeException('Suspension persistence failed.');
                }
                $this->audit($admin, $current, 'installer.suspended');
            }
            InstallerInvitation::query()->where('user_id', $current->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            AdminLoginChallenge::query()->where('user_id', $current->id)->whereNull('used_at')->update(['revoked_at' => now()]);
            if (Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $current->id)->delete();
            }
            $orders = Order::query()->where('user_id', $current->id)->orderBy('id')->lockForUpdate()->get();
            foreach ($orders as $order) {
                $order->forceFill(['token_hash' => null, 'token_expires_at' => null]);
                if (! $order->save()) {
                    throw new RuntimeException('Suspended order access revocation failed.');
                }
            }
            DB::afterCommit(function () use ($pending): void {
                foreach ($pending as $id) {
                    $attempt = PaymentAttempt::query()->find($id);
                    if ($attempt === null) {
                        continue;
                    }
                    if ($attempt->status === PaymentAttemptStatus::Paid) {
                        $this->recordSuspensionRace($attempt);
                    } elseif (config('services.stripe.enabled')) {
                        $this->expireAttempt($attempt);
                    }
                }
            });
        }, 3);
    }

    public function reactivate(User $installer, User $administrator): void
    {
        DB::transaction(function () use ($installer, $administrator): void {
            $admin = User::query()->whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->isActive() && $admin->isAdministrator(), 403);
            $current = User::query()->whereKey($installer->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->role === UserRole::Installer, 403);
            if ($current->suspended_at === null) {
                return;
            }
            // Una cuenta que nunca creó su contraseña vuelve a "invitación pendiente".
            $current->forceFill(['suspended_at' => null, 'is_active' => $current->activated_at !== null,
                'remember_token' => null, 'auth_version' => $current->auth_version + 1]);
            if (! $current->save()) {
                throw new RuntimeException('Reactivation persistence failed.');
            }
            $this->audit($admin, $current, 'installer.reactivated');
        }, 3);
    }

    /** Elimina la cuenta solo si no tiene pedidos; los pedidos conservan el historial financiero. */
    public function delete(User $installer, User $administrator): void
    {
        DB::transaction(function () use ($installer, $administrator): void {
            $admin = User::query()->whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->isActive() && $admin->isAdministrator(), 403);
            $current = User::query()->whereKey($installer->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->role === UserRole::Installer, 403);
            if (Order::query()->where('user_id', $current->id)->exists()) {
                throw ValidationException::withMessages(['installer' => 'Este instalador tiene pedidos y no se puede eliminar. Suspende su acceso para conservar el historial.']);
            }
            $this->audit($admin, $current, 'installer.deleted');
            $notifications = OrderNotification::query()->where('user_id', $current->id)->pluck('id');
            NotificationAttempt::query()->where(fn ($query) => $query->where('user_id', $current->id)->orWhereIn('order_notification_id', $notifications))->delete();
            OrderNotification::query()->where('user_id', $current->id)->delete();
            InstallerInvitation::query()->where('user_id', $current->id)->delete();
            AdminLoginChallenge::query()->where('user_id', $current->id)->delete();
            if (Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $current->id)->delete();
            }
            if (! $current->delete()) {
                throw new RuntimeException('Installer deletion failed.');
            }
        }, 3);
    }

    public function expireAttempt(PaymentAttempt $attempt): void
    {
        $gateway = app(StripeGateway::class);
        $gateway->assertOutsideTransaction();
        try {
            $sessionId = $attempt->stripe_session_id;
            if ($sessionId === null && $attempt->first_sent_at !== null) {
                $sessionId = $gateway->findCheckout($attempt)?->id;
            }
            if ($sessionId === null) {
                return;
            }
            try {
                $session = $gateway->expireCheckout($sessionId);
            } catch (DefinitiveStripeFailure) {
                $session = $gateway->retrieveCheckout($sessionId);
            }
            $gateway->validateSession($attempt, $session);
            $event = new StripeEvent;
            $event->forceFill(['stripe_event_id' => 'suspension:'.$attempt->reference, 'type' => 'checkout.session.completed',
                'payload' => ['api_version' => config('services.stripe.api_version'), 'livemode' => (bool) config('services.stripe.livemode'),
                    'created' => now()->timestamp, 'data' => ['object' => ['id' => $session->id]]]]);
            app(PaymentResultApplier::class)->apply($attempt, app(PaymentResultResolver::class)->resolve($event));
        } catch (\Throwable) {
            // Revocation is durable; unknown remote money remains recoverable.
        }
    }

    private function recordSuspensionRace(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $order = Order::query()->whereKey($attempt->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->where('payment_attempt_id', $attempt->id)->first();
            if ($payment === null) {
                return;
            }
            $key = hash('sha256', $order->reference.'|payment_after_suspension|'.$payment->stripe_charge_id);
            if (! FinancialIncident::query()->where('incident_key', $key)->exists()) {
                $incident = new FinancialIncident;
                $incident->forceFill(['order_id' => $order->id, 'payment_attempt_id' => $attempt->id, 'incident_key' => $key,
                    'kind' => 'payment_after_suspension', 'remote_id' => $payment->stripe_charge_id, 'reason' => 'Payment won the suspension race; money preserved.']);
                if (! $incident->save()) {
                    throw new RuntimeException('Suspension race incident persistence failed.');
                }
            }
        }, 3);
    }

    private function audit(User $actor, User $subject, string $action): void
    {
        $entry = AuditEntry::create(['actor_id' => $actor->id, 'entity_type' => 'user', 'entity_id' => $subject->id,
            'action' => $action, 'changes' => ['role' => $subject->role->value, 'is_active' => $subject->is_active]]);
        if (! $entry->exists) {
            throw new RuntimeException('Access audit persistence failed.');
        }
    }
}
