<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderNotificationController extends Controller
{
    public function store(Order $order): RedirectResponse
    {
        $notification = $this->requestNotification($order, null);
        app(NotificationDispatcher::class)->attempt($notification);

        return redirect()->route('admin.dashboard')->with('status', 'Aviso de recogida solicitado.');
    }

    public function resend(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['confirm_resend' => ['required', 'accepted'], 'intention_key' => ['required', 'uuid'], 'confirm_possible_duplicate' => ['required', 'accepted']]);
        $notification = $this->requestNotification($order, $data['intention_key']);
        app(NotificationDispatcher::class)->attempt($notification);

        return redirect()->route('admin.dashboard')->with('status', 'Reenvío registrado; consulta el historial de avisos.');
    }

    private function requestNotification(Order $order, ?string $intention): OrderNotification
    {
        return DB::transaction(function () use ($order, $intention): OrderNotification {
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $reason = app(NotificationDispatcher::class)->pickupBlockReason($current);
            if ($reason !== null) {
                throw ValidationException::withMessages(['notification' => 'El aviso está bloqueado por el estado financiero del pedido.']);
            }
            $key = 'pickup:'.$order->reference.($intention === null ? '' : ':resend:'.strtolower($intention));
            $existing = OrderNotification::query()->where('notification_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($intention !== null) {
                $original = OrderNotification::query()->where('notification_key', 'pickup:'.$order->reference)->lockForUpdate()->first();
                if ($original === null || ! in_array($original->status, [NotificationStatus::Accepted, NotificationStatus::Uncertain, NotificationStatus::Failed], true)) {
                    throw ValidationException::withMessages(['notification' => 'Espera a resolver el aviso inicial antes de solicitar un reenvío.']);
                }
            }
            $notification = new OrderNotification;
            $notification->forceFill(['order_id' => $current->id, 'notification_key' => $key, 'type' => 'pickup', 'destination' => $current->contact_email,
                'status' => NotificationStatus::Pending, 'requested_at' => now(), 'next_attempt_at' => now()]);
            if (! $notification->save()) {
                throw new RuntimeException('Could not persist pickup notification.');
            }
            if ($intention !== null) {
                $audit = AuditEntry::create(['actor_id' => request()->user()->id, 'action' => 'pickup_notification.resent', 'entity_type' => OrderNotification::class,
                    'entity_id' => $notification->id, 'changes' => ['order_reference' => $current->reference, 'possible_duplicate_acknowledged' => true]]);
                if (! $audit->exists) {
                    throw new RuntimeException('Could not persist resend audit.');
                }
            }

            return $notification;
        }, 3);
    }
}
