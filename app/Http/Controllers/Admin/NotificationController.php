<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\NotificationAttempt;
use App\Models\OrderNotification;
use App\Models\StripeEvent;
use App\Services\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(): View
    {
        $operations = [];
        foreach (['payments' => 'Pagos', 'notifications' => 'Avisos'] as $key => $label) {
            $metrics = Cache::store(config('store.recovery.cache_store', 'database'))->get('store:operations:'.$key, []);
            $last = $metrics['last_success_at'] ?? null;
            $operations[] = ['label' => $label, 'last' => $last, 'errors' => (int) ($metrics['errors'] ?? 0),
                'health' => $last === null ? 'Sin evidencia de ejecución' : (CarbonImmutable::parse($last)->lte(now()->subMinutes(45)) ? 'Retrasado' : 'Reciente')];
        }
        $notifications = OrderNotification::query()->with('order')->latest('id')->paginate(25);
        $attempts = NotificationAttempt::query()->whereIn('order_notification_id', $notifications->modelKeys())->orderBy('id')->get()->groupBy('order_notification_id');
        $pendingEvents = StripeEvent::query()->where('status', 'pending')->count();

        return view('admin.notifications.index', compact('notifications', 'operations', 'pendingEvents', 'attempts'));
    }

    public function retry(OrderNotification $notification): RedirectResponse
    {
        if (! in_array($notification->status, [NotificationStatus::Pending, NotificationStatus::Failed], true)) {
            throw ValidationException::withMessages(['notification' => 'Este aviso requiere revisar su resultado antes de solicitar otro envío.']);
        }
        $audit = AuditEntry::create(['actor_id' => request()->user()->id, 'action' => 'notification_retry.requested',
            'entity_type' => OrderNotification::class, 'entity_id' => $notification->id, 'changes' => ['type' => $notification->type]]);
        if (! $audit->exists) {
            throw new \RuntimeException('Notification audit could not be persisted.');
        }
        app(NotificationDispatcher::class)->attempt($notification);

        return redirect()->route('admin.notifications.index')->with('status', 'Intento solicitado; revisa su resultado y fecha de próximo intento.');
    }
}
