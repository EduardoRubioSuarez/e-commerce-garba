<x-layout title="Detalle del pedido">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
        $dt = fn ($value): string => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : 'No registrado';
        $statusLabel = match($order->status) { \App\Enums\OrderStatus::Paid => 'Pagado', \App\Enums\OrderStatus::ClosedUnpaid => 'Cerrado sin pago', default => 'Pendiente' };
        $statusClass = match($order->status) { \App\Enums\OrderStatus::Paid => 'bg-emerald-50 text-emerald-800 ring-emerald-200', \App\Enums\OrderStatus::ClosedUnpaid => 'bg-slate-100 text-slate-700 ring-slate-200', default => 'bg-amber-50 text-amber-800 ring-amber-200' };
        $tierLabel = match($order->tier->value) { 'public' => 'Público', 'installer' => 'Instalador', default => $order->tier->value };
        $blocked = app(\App\Services\NotificationDispatcher::class)->pickupBlockReason($order);
    @endphp

    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-3xl font-semibold">Detalle del pedido</h1>
            <p class="mt-2 break-all font-mono text-sm text-slate-500">{{ $order->reference }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $statusClass }}">{{ $statusLabel }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-200">Tarifa {{ $tierLabel }}</span>
        </div>
    </header>

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
        <div class="grid min-w-0 gap-6">
            <section class="rounded-lg border border-slate-200 bg-white p-6" aria-labelledby="original">
                <h2 id="original" class="text-xl font-semibold">Productos</h2>
                <ul class="mt-4 divide-y divide-slate-200">
                    @foreach($order->lines as $line)
                        <li class="flex items-start justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="break-words font-medium">{{ $line->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $line->quantity }} {{ $line->quantity === 1 ? 'unidad' : 'unidades' }}</p>
                            </div>
                            <p class="shrink-0 font-medium tabular-nums">{{ $money($line->total_cents) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-2 space-y-2 border-t border-slate-200 pt-4 text-sm">
                    @foreach(['subtotal_cents' => 'Subtotal sin IVA', 'discount_cents' => 'Descuento', 'tax_cents' => 'IVA'] as $field => $label)
                        <div class="flex justify-between gap-4"><dt class="text-slate-600">{{ $label }}</dt><dd class="tabular-nums">{{ $money($order->$field) }}</dd></div>
                    @endforeach
                    @if($order->coupon_snapshot)
                        <div class="flex justify-between gap-4"><dt class="text-slate-600">Cupón</dt><dd class="font-mono">{{ $order->coupon_snapshot['code'] ?? 'Aplicado' }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-lg font-semibold"><dt>Total aceptado</dt><dd class="tabular-nums">{{ $money($order->total_cents) }} <span class="text-sm font-normal text-slate-500">{{ $order->currency }}</span></dd></div>
                </dl>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6" aria-labelledby="finanzas">
                <h2 id="finanzas" class="text-xl font-semibold">Resumen financiero</h2>
                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-600">Cobrado</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money($gross) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-600">Reembolsado</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money($refunded) }}</dd></div>
                    <div class="rounded-lg bg-slate-50 p-4"><dt class="text-sm text-slate-600">Neto</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ $money($gross - $refunded) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Montos en {{ $order->currency }}. Reflejan los eventos registrados. Si falta alguno, consulta Stripe Dashboard como referencia financiera y para gestionar devoluciones.</p>
                @foreach($payments as $payment)
                    <div class="mt-4 rounded-lg border border-slate-200 p-4 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-medium">Cobro</p>
                            <p class="font-semibold tabular-nums">{{ $money($payment->amount_cents) }} {{ $payment->currency }}</p>
                        </div>
                        <dl class="mt-2 space-y-1 text-slate-600">
                            <div class="flex flex-wrap gap-x-2"><dt>Cargo:</dt><dd class="break-all font-mono text-xs">{{ $payment->stripe_charge_id }}</dd></div>
                            <div class="flex flex-wrap gap-x-2"><dt>Intención de pago:</dt><dd class="break-all font-mono text-xs">{{ $payment->stripe_payment_intent_id }}</dd></div>
                        </dl>
                        @foreach($payment->refunds as $refund)
                            <p class="mt-3 break-all border-t border-slate-200 pt-3">Reembolso <span class="font-mono text-xs">{{ $refund->stripe_refund_id }}</span>, {{ $refund->status }}, <span class="tabular-nums">{{ $money($refund->amount_cents) }}</span></p>
                        @endforeach
                    </div>
                @endforeach
                @foreach($incidents as $incident)
                    <p class="mt-3 break-words rounded-lg bg-red-50 p-3 text-sm text-red-800">Incidencia {{ $incident->kind }} <span class="font-mono text-xs">{{ $incident->remote_id }}</span>. {{ $incident->resolved_at ? 'Revisada' : 'Pendiente de revisión' }}</p>
                @endforeach
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6" aria-labelledby="intentos">
                <h2 id="intentos" class="text-xl font-semibold">Intentos de pago</h2>
                <p class="mt-2 text-sm text-slate-600">Cada vez que el cliente abre el pago en Stripe se registra un intento. Solo uno puede quedar pagado.</p>
                @forelse($order->paymentAttempts->sortByDesc('created_at')->values() as $attempt)
                    @php
                        $meta = match($attempt->status) {
                            \App\Enums\PaymentAttemptStatus::Paid => ['Pagado', 'El pago se completó correctamente.', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
                            \App\Enums\PaymentAttemptStatus::Open => ['Esperando pago', 'El cliente aún no completa el pago en Stripe.', 'bg-amber-50 text-amber-800 ring-amber-200'],
                            \App\Enums\PaymentAttemptStatus::Creating => ['Iniciando', 'Se está creando la sesión de pago.', 'bg-amber-50 text-amber-800 ring-amber-200'],
                            \App\Enums\PaymentAttemptStatus::Unknown => ['Por confirmar', 'No se sabe si el cliente pagó. Conviene consultar a Stripe.', 'bg-amber-50 text-amber-800 ring-amber-200'],
                            \App\Enums\PaymentAttemptStatus::Failed => ['Fallido', 'El pago no se pudo completar.', 'bg-red-50 text-red-800 ring-red-200'],
                            \App\Enums\PaymentAttemptStatus::Expired => ['Expirado', 'El cliente no pagó a tiempo y la sesión venció.', 'bg-slate-100 text-slate-700 ring-slate-200'],
                        };
                    @endphp
                    <div class="mt-4 rounded-lg border border-slate-200 p-4 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">Intento {{ $order->paymentAttempts->count() - $loop->index }}</p>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $meta[2] }}">{{ $meta[0] }}</span>
                        </div>
                        <p class="mt-1 text-slate-600">{{ $meta[1] }}</p>
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div><dt class="text-slate-500">Monto</dt><dd class="mt-0.5 font-medium tabular-nums">{{ $money($attempt->amount_cents) }} {{ $attempt->currency }}</dd></div>
                            <div><dt class="text-slate-500">Iniciado</dt><dd class="mt-0.5 font-medium">{{ $dt($attempt->first_sent_at ?? $attempt->created_at) }}</dd></div>
                            <div><dt class="text-slate-500">{{ $attempt->status === \App\Enums\PaymentAttemptStatus::Open ? 'Vence' : 'Vencimiento' }}</dt><dd class="mt-0.5 font-medium">{{ $dt($attempt->expires_at) }}</dd></div>
                            <div><dt class="text-slate-500">Última verificación con Stripe</dt><dd class="mt-0.5 font-medium">{{ $attempt->last_checked_at ? $dt($attempt->last_checked_at) : 'Aún no verificado' }}</dd></div>
                        </dl>
                        @if($attempt->last_error)<p class="mt-3 break-words rounded-lg bg-red-50 p-3 text-red-800">Error registrado: {{ $attempt->last_error }}</p>@endif
                        @if(\Illuminate\Support\Facades\Route::has('admin.payment-recovery.store') && in_array($attempt->status, [\App\Enums\PaymentAttemptStatus::Creating, \App\Enums\PaymentAttemptStatus::Open, \App\Enums\PaymentAttemptStatus::Unknown], true))
                            <form method="POST" action="{{ route('admin.payment-recovery.store', $attempt) }}" class="mt-3">@csrf<button class="btn btn-primary">Consultar y recuperar pago pendiente</button></form>
                        @endif
                        <details class="mt-3 text-slate-600">
                            <summary class="cursor-pointer text-slate-500 hover:text-slate-900">Detalles técnicos</summary>
                            <dl class="mt-2 space-y-1">
                                <div class="flex flex-wrap gap-x-2"><dt>Referencia interna:</dt><dd class="break-all font-mono text-xs">{{ $attempt->reference }}</dd></div>
                                <div class="flex flex-wrap gap-x-2"><dt>Sesión de Stripe:</dt><dd class="break-all font-mono text-xs">{{ $attempt->stripe_session_id }}</dd></div>
                            </dl>
                        </details>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-600">Este pedido no tiene intentos de pago.</p>
                @endforelse
                @if($reservation)<p class="mt-4 text-sm text-slate-600">Derecho de cupón: <span class="font-medium text-slate-900">{{ $reservation->status->value }}</span></p>@endif
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6" aria-labelledby="eventos">
                <h2 id="eventos" class="text-xl font-semibold">Avisos de Stripe pendientes</h2>
                <p class="mt-2 text-sm text-slate-600">Avisos que Stripe envió sobre este pedido y que la tienda todavía no termina de procesar. Normalmente se resuelven solos.</p>
                @forelse($events as $event)
                    @php
                        $eventInfo = match(true) {
                            $event->type === 'checkout.session.completed' => ['El cliente completó el pago', 'Stripe avisa que el cliente terminó el pago en la pantalla de cobro.'],
                            $event->type === 'checkout.session.expired' => ['La sesión de pago venció', 'El cliente no pagó a tiempo.'],
                            $event->type === 'payment_intent.succeeded' => ['Pago confirmado', 'Stripe confirma que el dinero del pago fue aceptado.'],
                            $event->type === 'payment_intent.payment_failed' => ['Pago rechazado', 'La tarjeta no pudo ser cobrada.'],
                            $event->type === 'charge.succeeded' => ['Cargo realizado', 'Se hizo el cargo a la tarjeta del cliente.'],
                            str_starts_with($event->type, 'charge.dispute.') => ['Disputa del cargo', 'El cliente o su banco cuestionó el cobro. Revisa Stripe Dashboard.'],
                            str_starts_with($event->type, 'refund.') || $event->type === 'charge.refunded' => ['Reembolso', 'Stripe informa un cambio en un reembolso de este pedido.'],
                            default => ['Aviso de Stripe', 'Tipo de evento: '.$event->type],
                        };
                        $eventStatus = match($event->status) {
                            'pending' => ['Pendiente de procesar', 'bg-amber-50 text-amber-800 ring-amber-200'],
                            'processed' => ['Procesado', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
                            'ignored' => ['Ignorado', 'bg-slate-100 text-slate-700 ring-slate-200'],
                            default => [$event->status, 'bg-slate-100 text-slate-700 ring-slate-200'],
                        };
                    @endphp
                    <div class="mt-4 rounded-lg border border-slate-200 p-4 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">{{ $eventInfo[0] }}</p>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $eventStatus[1] }}">{{ $eventStatus[0] }}</span>
                        </div>
                        <p class="mt-1 text-slate-600">{{ $eventInfo[1] }}</p>
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div><dt class="text-slate-500">Recibido</dt><dd class="mt-0.5 font-medium">{{ $dt($event->received_at) }}</dd></div>
                            <div><dt class="text-slate-500">Última verificación con Stripe</dt><dd class="mt-0.5 font-medium">{{ $event->last_checked_at ? $dt($event->last_checked_at) : 'Aún no verificado' }}</dd></div>
                        </dl>
                        <details class="mt-3 text-slate-600">
                            <summary class="cursor-pointer text-slate-500 hover:text-slate-900">Detalles técnicos</summary>
                            <dl class="mt-2 space-y-1">
                                <div class="flex flex-wrap gap-x-2"><dt>Tipo de evento:</dt><dd class="break-all font-mono text-xs">{{ $event->type }}</dd></div>
                                <div class="flex flex-wrap gap-x-2"><dt>ID del evento:</dt><dd class="break-all font-mono text-xs">{{ $event->stripe_event_id }}</dd></div>
                            </dl>
                        </details>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-600">No hay avisos de Stripe pendientes para este pedido.</p>
                @endforelse
                <div class="mt-3">{{ $events->links() }}</div>
            </section>
        </div>

        <aside class="grid min-w-0 content-start gap-6" aria-label="Cliente y recogida">
            <section class="rounded-lg border border-slate-200 bg-white p-6" aria-labelledby="contacto">
                <h2 id="contacto" class="text-xl font-semibold">Contacto autorizado</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div><dt class="text-slate-500">Nombre</dt><dd class="mt-1 break-words font-medium">{{ $order->contact_name }}</dd></div>
                    <div><dt class="text-slate-500">Correo</dt><dd class="mt-1 break-all font-medium">{{ $order->contact_email }}</dd></div>
                    <div><dt class="text-slate-500">Teléfono</dt><dd class="mt-1 break-words font-medium">{{ $order->phone_original }}</dd></div>
                    <div><dt class="text-slate-500">Fecha del pedido</dt><dd class="mt-1 font-medium">{{ $dt($order->created_at) }}</dd></div>
                </dl>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6" aria-labelledby="avisos">
                <h2 id="avisos" class="text-xl font-semibold">Aviso de recogida</h2>
                <p class="mt-2 text-sm text-slate-600">Cuando el pedido esté listo en el local, envía un correo al cliente para que pase a recogerlo.</p>
                @if($blocked)<p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">Aviso de recogida bloqueado: {{ $blocked }}</p>@endif
                <form method="POST" action="{{ route('admin.order-notifications.store', ['order' => $order->reference]) }}" class="mt-4">@csrf<button @disabled($blocked || $notifications->where('type', 'pickup')->isNotEmpty()) class="btn btn-primary w-full">Avisar para recoger</button></form>
                <h3 class="mt-6 text-sm font-semibold text-slate-500">Historial</h3>
                @forelse($notifications as $notification)
                    <div class="mt-3 rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium">{{ $notification->type }}</p>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">{{ $notification->status->value }}</span>
                        </div>
                        <p class="mt-2 text-slate-600">Solicitado {{ $dt($notification->requested_at) }}</p>
                        <p class="text-slate-600">Aceptado {{ $dt($notification->accepted_at) }}</p>
                        @if($notification->cancellation_reason ?? $notification->blocked_reason)<p class="mt-1 break-words text-slate-600">{{ $notification->cancellation_reason ?? $notification->blocked_reason }}</p>@endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-600">Aún no se ha enviado ningún aviso.</p>
                @endforelse
            </section>
        </aside>
    </div>
</x-layout>
