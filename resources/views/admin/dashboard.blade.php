<x-layout title="Panel de administración">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
        $sections = [
            'Ventas' => [
                ['admin.orders.index', 'Pedidos', 'Consulta compras, pagos y reembolsos.'],
                ['admin.coupons.index', 'Cupones', 'Crea y administra códigos de descuento.'],
                ['admin.pricing.edit', 'Precios', 'Ajusta márgenes y precios por tarifa.'],
            ],
            'Catálogo' => [
                ['admin.products.index', 'Productos', 'Agrega, edita y publica productos.'],
                ['admin.categories.index', 'Categorías', 'Organiza cómo se agrupan los productos.'],
            ],
            'Tienda' => [
                ['admin.settings.edit', 'Configuración del local', 'Dirección, horario, contacto y políticas.'],
                ['admin.installers.index', 'Instaladores', 'Invita y administra cuentas de instaladores.'],
                ['admin.notifications.index', 'Diagnóstico y avisos', 'Revisa los correos enviados a clientes.'],
            ],
        ];
        $orders = \App\Models\Order::query()->where('status', \App\Enums\OrderStatus::Paid)->withSum('lines as items_count', 'quantity')->latest('id')->limit(25)->get();
        $notices = \App\Models\OrderNotification::query()->whereIn('notification_key', $orders->map(fn ($o) => 'pickup:'.$o->reference)->all())->get()->keyBy('notification_key');
        $dispatcher = app(\App\Services\NotificationDispatcher::class);
    @endphp

    <h1 class="text-3xl font-semibold tracking-tight">Panel de administración</h1>
    <p class="mt-2 text-slate-600">Hola, {{ auth()->user()->name }}.</p>

    <div class="mt-8 grid gap-6 md:grid-cols-3">
        @foreach($sections as $group => $links)
            <section aria-labelledby="grupo-{{ $loop->index }}" class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 id="grupo-{{ $loop->index }}" class="text-lg font-semibold">{{ $group }}</h2>
                <ul class="mt-3 divide-y divide-slate-200">
                    @foreach($links as [$route, $label, $hint])
                        <li>
                            <a href="{{ route($route) }}" class="group -mx-2 flex min-h-11 flex-col rounded-md px-2 py-3 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">
                                <span class="font-semibold text-blue-900 group-hover:underline">{{ $label }}</span>
                                <span class="mt-0.5 text-sm text-slate-600">{{ $hint }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    <section class="mt-8 rounded-lg border border-slate-200 bg-white p-6 sm:p-8" aria-labelledby="avisos-recogida">
        <h2 id="avisos-recogida" class="text-xl font-semibold">Avisar que el pedido está listo para recoger</h2>
        <p class="mt-2 max-w-2xl leading-7 text-slate-600">El cliente ya pagó y recibió su confirmación. Cuando su pedido esté preparado en el local, pulsa <strong class="font-semibold text-slate-900">Avisar para recoger</strong>. Se le enviará un correo para que pase por él. Solo se puede avisar una vez; si el correo no llegó, podrás reenviarlo.</p>
        <ul class="mt-6 divide-y divide-slate-200 border-t border-slate-200">
            @forelse($orders as $order)
                @php
                    $blocked = $dispatcher->pickupBlockReason($order);
                    $notice = $notices->get('pickup:'.$order->reference);
                    $items = (int) $order->items_count;
                    $blockedText = match($blocked) {
                        'fully_refunded' => 'Este pedido fue reembolsado por completo, por lo que no se puede avisar.',
                        'financial_incident' => 'Hay una incidencia de pago sin resolver. Revísala en el detalle del pedido antes de avisar.',
                        default => 'Este pedido no se puede avisar por su estado de pago.',
                    };
                    [$noticeLabel, $noticeHint, $noticeClass] = match($notice?->status) {
                        null => ['Sin avisar', 'Aún no se ha enviado el aviso de recogida.', 'bg-slate-100 text-slate-700 ring-slate-200'],
                        \App\Enums\NotificationStatus::Accepted => ['Aviso enviado', 'El proveedor de correo aceptó el mensaje. Eso no garantiza que llegue al buzón.', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
                        \App\Enums\NotificationStatus::Uncertain => ['Envío por confirmar', 'No se sabe si el correo salió. Reenviarlo podría duplicar el mensaje.', 'bg-amber-50 text-amber-800 ring-amber-200'],
                        \App\Enums\NotificationStatus::Failed => ['Falló el envío', 'El correo no se pudo enviar. Puedes reenviarlo.', 'bg-red-50 text-red-800 ring-red-200'],
                        \App\Enums\NotificationStatus::Cancelled => ['Cancelado', 'El aviso se canceló y no se enviará.', 'bg-slate-100 text-slate-700 ring-slate-200'],
                        default => ['En cola', 'El correo está por enviarse.', 'bg-amber-50 text-amber-800 ring-amber-200'],
                    };
                @endphp
                <li class="py-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold">{{ $order->contact_name ?: 'Cliente sin nombre' }}</p>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $noticeClass }}">{{ $noticeLabel }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $items }} {{ $items === 1 ? 'producto' : 'productos' }}, {{ $money($order->total_cents) }} {{ $order->currency }}. Comprado el {{ $order->created_at->format('Y-m-d H:i') }}.</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $blocked ? $blockedText : $noticeHint }}</p>
                            <a href="{{ route('admin.orders.show', ['order' => $order->reference]) }}" class="mt-1 inline-block text-sm font-medium text-blue-900 underline">Ver pedido #{{ \Illuminate\Support\Str::substr($order->reference, 0, 8) }}</a>
                        </div>
                        <form action="{{ route('admin.order-notifications.store', ['order' => $order->reference]) }}" method="POST" class="shrink-0">
                            @csrf
                            <button type="submit" @disabled($blocked || $notice) class="btn btn-primary w-full sm:w-auto">Avisar para recoger</button>
                        </form>
                    </div>
                    @if($notice && ! $blocked && in_array($notice->status, [\App\Enums\NotificationStatus::Accepted, \App\Enums\NotificationStatus::Uncertain, \App\Enums\NotificationStatus::Failed], true))
                        <details class="mt-3 text-sm">
                            <summary class="cursor-pointer font-medium text-blue-900 underline">El cliente no recibió el correo</summary>
                            <form action="{{ route('admin.order-notifications.resend', ['order' => $order->reference]) }}" method="POST" class="mt-3 grid gap-3">
                                @csrf
                                <input type="hidden" name="intention_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="confirm_resend" value="1" required>Confirmo que quiero enviar el aviso otra vez.</label>
                                <label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="confirm_possible_duplicate" value="1" required>Entiendo que el cliente podría recibir el mensaje duplicado.</label>
                                <button type="submit" class="btn btn-secondary justify-self-start">Reenviar aviso de recogida</button>
                            </form>
                        </details>
                    @endif
                </li>
            @empty
                <li class="py-6 text-slate-600">Aún no hay pedidos pagados. Cuando un cliente pague, aparecerá aquí para avisarle.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8 rounded-lg border border-slate-200 bg-white p-6 sm:p-8" aria-labelledby="dispositivos">
        <h2 id="dispositivos" class="text-xl font-semibold">Dispositivos recordados</h2>
        <p class="mt-2 max-w-2xl leading-7 text-slate-600">Los dispositivos recordados omiten el código de acceso por 30 días. Cambiar tu contraseña también los olvida.</p>
        <form method="POST" action="{{ route('admin.second-factor.forget') }}" class="mt-4">@csrf<button type="submit" class="btn btn-danger">Olvidar todos los dispositivos</button></form>
    </section>
</x-layout>
