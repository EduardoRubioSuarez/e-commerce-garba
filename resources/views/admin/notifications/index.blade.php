<x-layout title="Diagnóstico y avisos">
    @php
        $dt = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : null;
        $ago = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->locale('es')->diffForHumans() : null;
        $typeLabels = ['pickup' => 'Aviso de recogida', 'purchase' => 'Confirmación de compra', 'admin_login_code' => 'Código de acceso de administrador', 'installer_invitation' => 'Invitación a instalador', 'password_reset' => 'Restablecer contraseña'];
        $reasonLabels = ['not_paid' => 'El pedido no está pagado.', 'fully_refunded' => 'El pedido fue reembolsado por completo.', 'financial_incident' => 'Hay una incidencia de pago sin resolver.'];
        $attemptLabels = ['prepared' => 'Preparado', 'sending' => 'Enviando', 'accepted' => 'Aceptado por el proveedor', 'uncertain' => 'Resultado incierto', 'failed' => 'Falló'];
        $good = 'bg-emerald-50 text-emerald-800 ring-emerald-200';
        $warn = 'bg-amber-50 text-amber-800 ring-amber-200';
        $bad = 'bg-red-50 text-red-800 ring-red-200';
        $neutral = 'bg-slate-100 text-slate-700 ring-slate-200';
        $healthClass = ['Reciente' => $good, 'Retrasado' => $bad];
        $operationInfo = [
            'Pagos' => ['Confirmación de pagos', 'Revisa en Stripe los pagos que quedaron sin confirmar.'],
            'Avisos' => ['Envío de avisos', 'Manda los correos pendientes a los clientes.'],
        ];
    @endphp

    <header>
        <h1 class="text-3xl font-semibold">Diagnóstico y avisos</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-600">Comprueba que los pagos se confirmen y que los correos lleguen a los clientes.</p>
    </header>

    <section aria-labelledby="salud" class="mt-8">
        <h2 id="salud" class="text-xl font-semibold">Estado del sistema</h2>
        <p class="mt-1 text-sm text-slate-600">Las tareas automáticas corren cada 15 minutos. Si pasan más de 45 minutos sin terminar bien, se marcan como retrasadas.</p>
        <ul class="mt-4 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
            @foreach($operations as $operation)
                @php
                    [$title, $what] = $operationInfo[$operation['label']] ?? [$operation['label'], ''];
                    $healthLabel = $operation['health'] === 'Sin evidencia de ejecución' ? 'Sin ejecuciones' : $operation['health'];
                @endphp
                <li class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 p-5">
                    <div class="min-w-0 max-w-xl">
                        <h3 class="text-base font-semibold">{{ $title }}</h3>
                        <p class="mt-0.5 text-sm text-slate-600">{{ $what }}</p>
                        <p class="mt-2 text-sm text-slate-700">
                            @if($operation['last'])
                                Última vez correcta: <time datetime="{{ \Illuminate\Support\Carbon::parse($operation['last'])->toIso8601String() }}" title="{{ $dt($operation['last']) }}" class="font-medium">{{ $ago($operation['last']) }}</time>
                            @else
                                Aún no se registra ninguna ejecución. Revisa que el programador de tareas esté activo.
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm {{ $operation['errors'] > 0 ? 'font-medium text-red-800' : 'text-slate-600' }}">
                            {{ $operation['errors'] > 0 ? $operation['errors'].' '.($operation['errors'] === 1 ? 'error' : 'errores').' en el último lote' : 'Sin errores en el último lote' }}
                        </p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $healthClass[$operation['health']] ?? $warn }}">{{ $healthLabel }}</span>
                </li>
            @endforeach
            <li class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 p-5">
                <div class="min-w-0 max-w-xl">
                    <h3 class="text-base font-semibold">Avisos de Stripe pendientes de procesar</h3>
                    <p class="mt-0.5 text-sm text-slate-600">Notificaciones de pago que Stripe envió y la tienda aún no aplica.</p>
                </div>
                <p class="flex items-center gap-3">
                    <span class="text-2xl font-semibold tabular-nums">{{ $pendingEvents }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $pendingEvents > 0 ? $warn : $good }}">{{ $pendingEvents > 0 ? 'Por procesar' : 'Al día' }}</span>
                </p>
            </li>
        </ul>
        <p class="mt-3 max-w-2xl text-sm text-slate-600">Si falta un pago, el Dashboard de Stripe es la referencia financiera. Que el proveedor de correo acepte un mensaje no garantiza que llegue al buzón del cliente.</p>
    </section>

    <section aria-labelledby="historial" class="mt-10">
        <h2 id="historial" class="text-xl font-semibold">Historial de correos</h2>
        <p class="mt-1 text-sm text-slate-600">Del más reciente al más antiguo. Las direcciones se ocultan por privacidad.</p>
        <ul class="mt-4 grid gap-3">
            @forelse($notifications as $notification)
                @php
                    [$label, $hint, $class] = match($notification->status) {
                        \App\Enums\NotificationStatus::Accepted => ['Enviado', null, $good],
                        \App\Enums\NotificationStatus::Uncertain => ['Por confirmar', 'No se sabe si el correo salió. Reenviarlo podría duplicarlo.', $warn],
                        \App\Enums\NotificationStatus::Failed => ['Falló', 'El correo no se pudo enviar.', $bad],
                        \App\Enums\NotificationStatus::Cancelled => ['Cancelado', 'El correo se canceló y no se enviará.', $neutral],
                        \App\Enums\NotificationStatus::Claimed => ['Enviándose', 'El correo se está procesando ahora mismo.', $warn],
                        default => ['En cola', 'El correo está esperando para enviarse.', $warn],
                    };
                    $reason = $notification->blocked_reason ?? $notification->cancellation_reason;
                    $notificationAttempts = $attempts->get($notification->id, collect());
                    $canRetry = in_array($notification->status, [\App\Enums\NotificationStatus::Pending, \App\Enums\NotificationStatus::Failed], true);
                    $canResend = $notification->type === 'pickup' && $notification->order && in_array($notification->status, [\App\Enums\NotificationStatus::Accepted, \App\Enums\NotificationStatus::Uncertain, \App\Enums\NotificationStatus::Failed], true);
                @endphp
                <li class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold">{{ $typeLabels[$notification->type] ?? $notification->type }}</h3>
                            <p class="mt-0.5 text-sm text-slate-600">
                                Para {{ mb_substr($notification->destination, 0, 1) }}***@***
                                @if($notification->order)
                                    <span class="mx-1" aria-hidden="true">/</span><a class="font-medium text-blue-900 underline underline-offset-2" href="{{ route('admin.orders.show', ['order' => $notification->order->reference]) }}">Pedido #{{ \Illuminate\Support\Str::substr($notification->order->reference, 0, 8) }}</a>
                                @endif
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $class }}">{{ $label }}</span>
                    </div>

                    @if($hint)<p class="mt-3 text-sm text-slate-700">{{ $hint }}</p>@endif
                    @if($notification->last_error)<p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">El envío necesita revisión. Mira el resultado de los intentos antes de volver a enviarlo.</p>@endif
                    @if($reason)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">Motivo: {{ $reasonLabels[$reason] ?? $reason }}</p>@endif

                    <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                        <div><dt class="inline text-slate-500">Solicitado:</dt> <dd class="inline font-medium">{{ $dt($notification->requested_at) ?? 'sin fecha' }}</dd></div>
                        @if($notification->accepted_at)<div><dt class="inline text-slate-500">Aceptado:</dt> <dd class="inline font-medium">{{ $dt($notification->accepted_at) }}</dd></div>@endif
                        @if($notification->next_attempt_at)<div><dt class="inline text-slate-500">Próximo intento:</dt> <dd class="inline font-medium">{{ $dt($notification->next_attempt_at) }}</dd></div>@endif
                    </dl>

                    @if($canRetry)
                        <form method="POST" action="{{ route('admin.notifications.retry', $notification) }}" class="mt-4">@csrf<button class="btn btn-primary">Reintentar envío</button></form>
                    @endif

                    @if($notificationAttempts->isNotEmpty())
                        <details class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600">
                            <summary class="cursor-pointer font-medium text-blue-900 underline underline-offset-2">Intentos de envío ({{ $notificationAttempts->count() }})</summary>
                            <ol class="mt-2 space-y-1">
                                @foreach($notificationAttempts as $attempt)
                                    <li><span class="font-medium text-slate-800">Intento {{ $loop->iteration }}: {{ $attemptLabels[$attempt->status] ?? $attempt->status }}.</span> Inició {{ $dt($attempt->started_at) ?? 'sin fecha' }}, {{ $attempt->finished_at ? 'terminó '.$dt($attempt->finished_at) : 'sin terminar' }}.</li>
                                @endforeach
                            </ol>
                        </details>
                    @endif

                    @if($canResend)
                        <details class="mt-3 border-t border-slate-100 pt-3 text-sm">
                            <summary class="cursor-pointer font-medium text-blue-900 underline underline-offset-2">El cliente no recibió el correo</summary>
                            <form method="POST" action="{{ route('admin.order-notifications.resend', ['order' => $notification->order->reference]) }}" class="mt-3 grid gap-3">
                                @csrf<input type="hidden" name="intention_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="confirm_resend" value="1" required>Confirmo que quiero enviar el aviso otra vez.</label>
                                <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="confirm_possible_duplicate" value="1" required>Entiendo que el cliente podría recibir el mensaje duplicado.</label>
                                <button class="btn btn-secondary justify-self-start">Reenviar aviso de recogida</button>
                            </form>
                        </details>
                    @endif
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                    <p class="font-medium text-slate-900">Todavía no se ha enviado ningún correo.</p>
                    <p class="mt-1 text-sm">Aparecerán aquí cuando un cliente compre o se envíe una invitación.</p>
                </li>
            @endforelse
        </ul>
        <div class="mt-6">{{ $notifications->links() }}</div>
    </section>
</x-layout>
