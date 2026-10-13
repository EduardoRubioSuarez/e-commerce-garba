<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\PaymentAttempt;
use App\Services\PaymentReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentRecoveryController extends Controller
{
    public function store(PaymentAttempt $attempt): RedirectResponse
    {
        if (! in_array($attempt->status, [PaymentAttemptStatus::Creating, PaymentAttemptStatus::Open, PaymentAttemptStatus::Unknown], true)) {
            throw ValidationException::withMessages(['payment' => 'Solo se recuperan intentos de pago pendientes.']);
        }
        $audit = AuditEntry::create(['actor_id' => request()->user()->id, 'action' => 'payment_recovery.requested',
            'entity_type' => PaymentAttempt::class, 'entity_id' => $attempt->id, 'changes' => ['attempt_reference' => $attempt->reference]]);
        if (! $audit->exists) {
            throw new \RuntimeException('Recovery audit could not be persisted.');
        }
        try {
            app(PaymentReconciler::class)->recoverAttempt($attempt);
        } catch (Throwable) {
            return redirect()->route('admin.orders.show', ['order' => $attempt->order->reference])
                ->withErrors(['payment' => 'No se obtuvo un resultado confirmado. Conservamos el intento para recuperación.']);
        }

        return redirect()->route('admin.orders.show', ['order' => $attempt->order->reference])->with('status', 'Consulta completada; revisa el estado financiero registrado.');
    }
}
