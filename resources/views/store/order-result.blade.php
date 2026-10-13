<x-layout :title="$embeddedCheckout ? 'Completa tu compra' : 'Consulta del pedido'">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
    @endphp
    <x-checkout-progress :step="4" />
    <p class="eyebrow">{{ $embeddedCheckout ? 'Último paso' : 'Tu pedido' }}</p>
    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $embeddedCheckout ? 'Completa tu compra' : match($data['status']) { 'paid' => 'Pago confirmado', 'closed_unpaid' => 'Pedido cerrado sin pago', default => 'Estamos verificando tu pago' } }}</h1>
    @if($data['status'] === 'pending')<p class="mt-3 text-sm text-slate-600">Pago pendiente</p>@endif
    @if($canResume)
        <p class="mt-4 max-w-2xl text-slate-600">El resultado del intento aún no está confirmado. Puedes consultarlo conservando el mismo pedido.</p>
        <form action="{{ route('orders.resume', ['order'=>$data['reference']]) }}" method="POST" class="mt-4">
            @csrf
            <button type="submit" class="min-h-11 rounded-md border border-blue-800 px-5 py-3 font-semibold text-blue-800 focus-visible:outline-2 focus-visible:outline-blue-700">Consultar intento de pago</button>
        </form>
    @endif
    <div class="mt-8 grid gap-8 {{ $embeddedCheckout ? 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]' }}">
        @if($embeddedCheckout)
            <div class="min-w-0 space-y-6">
        @endif
        <section aria-labelledby="detalle-pedido" class="min-w-0 border-y border-slate-200 py-6">
            <h2 id="detalle-pedido" class="text-xl font-semibold">Tu pedido</h2>
            <ul class="mt-4 divide-y divide-slate-200">
                @foreach($data['lines'] as $line)
                    <li class="flex flex-wrap justify-between gap-3 py-4"><div class="min-w-0"><p class="break-words font-medium">{{ $line['name'] }}</p><p class="mt-1 text-sm text-slate-600">Cantidad: {{ $line['quantity'] }}</p></div><p class="break-all font-semibold tabular-nums">{{ $money($line['total_cents']) }} {{ $data['currency'] }}</p></li>
                @endforeach
            </ul>
            <h2 class="mt-6 text-xl font-semibold">Contacto registrado</h2>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach(['name' => 'Nombre', 'email' => 'Correo', 'phone' => 'Teléfono'] as $key => $label)
                    <div><dt class="font-medium">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-600">{{ $data['contact'][$key] }}</dd></div>
                @endforeach
            </dl>
        </section>
        <aside aria-label="Importe registrado" class="purchase-summary min-w-0 self-start">
            <h2 class="text-xl font-semibold">Importe del pedido</h2>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex flex-wrap justify-between gap-2"><dt>Subtotal sin IVA</dt><dd class="break-all tabular-nums">{{ $money($data['subtotal_cents']) }} {{ $data['currency'] }}</dd></div>
                <div class="flex flex-wrap justify-between gap-2"><dt>IVA</dt><dd class="break-all tabular-nums">{{ $money($data['tax_cents']) }} {{ $data['currency'] }}</dd></div>
                <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-3 text-lg font-semibold"><dt>Total</dt><dd class="break-all tabular-nums">{{ $money($data['total_cents']) }} {{ $data['currency'] }}</dd></div>
            </dl>
            <p class="mt-5 text-sm leading-6 text-slate-600">Solo recogida en local. Espera el aviso de recogida antes de acudir.</p>
            <a href="{{ route('store.information') }}" class="mt-2 inline-flex min-h-11 items-center text-sm text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Información de recogida</a>
        </aside>
        @if($embeddedCheckout)
            </div>
        @endif
        @if($embeddedCheckout)
            <section aria-labelledby="pago-tarjeta" class="purchase-panel min-w-0 self-start"
                data-stripe-checkout data-publishable-key="{{ $stripePublishableKey }}"
                data-session-url="{{ route('orders.embedded-checkout', ['order' => $data['reference']]) }}"
                data-result-url="{{ route('orders.show', ['order' => $data['reference']]) }}" data-csrf-token="{{ csrf_token() }}">
                <h2 id="pago-tarjeta" class="text-xl font-semibold">Paga con tarjeta</h2>
                <p data-checkout-status role="status" aria-live="polite" class="mt-3 text-sm text-slate-600">Cargando el formulario de pago seguro…</p>
                <div data-checkout-mount class="mt-4 min-w-0"></div>
                <button data-checkout-retry type="button" hidden class="mt-4 min-h-11 rounded-md border border-blue-800 px-5 py-3 font-semibold text-blue-800">Volver a cargar el formulario</button>
                <a href="{{ route('orders.show', ['order' => $data['reference']]) }}" class="mt-3 inline-flex min-h-11 items-center text-sm text-blue-800 underline">Actualizar estado del pedido</a>
                <noscript><p class="mt-4">Activa JavaScript para usar el formulario de pago seguro.</p></noscript>
            </section>
        @elseif($checkoutUrl)
            <a href="{{ $checkoutUrl }}" class="mt-6 inline-flex min-h-11 items-center rounded-md bg-blue-800 px-5 py-3 font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-700">Continuar al pago seguro</a>
        @endif
    </div>
    @if($data['status'] !== 'pending')
        <p class="mt-3 max-w-2xl leading-7 text-slate-600">{{ $data['status'] === 'paid' ? 'El pago está registrado. Tu correo de confirmación incluye la referencia de la compra. Espera el segundo correo antes de acudir al local.' : 'Este pedido se cerró sin un pago confirmado.' }}</p>
    @endif
    @if($privateLink && $data['status'] !== 'pending')
        <div class="mt-6 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">
            <p>Guarda este enlace privado; permite consultar el pedido durante {{ config('store.checkout.access_token_days', 7) }} días. Compártelo solo con quien deba consultar la compra.</p>
            <a href="{{ $privateLink }}" class="mt-2 inline-flex min-h-11 items-center font-medium underline focus-visible:outline-2 focus-visible:outline-blue-700">Enlace privado de consulta</a>
        </div>
    @endif
    <a href="{{ route('store.catalog') }}" class="mt-8 inline-flex min-h-11 items-center text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Volver al catálogo</a>
</x-layout>
