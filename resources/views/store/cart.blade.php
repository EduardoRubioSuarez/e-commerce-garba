<x-layout title="Carrito">
    @php
        $photos = $photos ?? collect();
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
    @endphp
    <x-checkout-progress :step="1" />
    <p class="eyebrow">Revisa tus equipos</p>
    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Tu carrito</h1>
    <p class="mt-3 max-w-2xl leading-7 text-slate-600">Los precios se recalculan con la tarifa vigente. Revisa el total cuando cambies de sesión o actualices los artículos.</p>
    @if($tier === \App\Enums\PriceTier::Installer)
        <p class="mt-3 text-sm font-medium text-blue-800">Tarifa de instalador</p>
    @endif
    @if($data['lines'] === [])
        <div class="mt-8 border-y border-slate-200 py-12">
            <h2 class="text-xl font-semibold">Tu carrito está vacío</h2>
            <a href="{{ route('store.catalog') }}" class="mt-4 inline-flex min-h-11 items-center text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Explorar equipos</a>
        </div>
    @else
        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
            <section aria-label="Artículos del carrito" class="cart-items min-w-0">
                @foreach($data['lines'] as $item)
                    @php
                        $hasQuantityError = $errors->has('quantity') && is_scalar(old('product_id')) && (string) old('product_id') === (string) $item['product_id'];
                    @endphp
                    <article class="cart-line" aria-labelledby="articulo-{{ $item['product_id'] }}">
                        <a href="{{ route('store.product', ['slug' => $item['slug']]) }}" class="cart-photo" tabindex="-1" aria-hidden="true">
                            @if($photos->has($item['product_id']))
                                <img src="{{ $photos[$item['product_id']]['url'] }}" alt="" loading="lazy" width="160" height="160">
                            @else
                                <span>Sin foto</span>
                            @endif
                        </a>
                        <div class="cart-body">
                            <div class="cart-head">
                                <h2 id="articulo-{{ $item['product_id'] }}"><a href="{{ route('store.product', ['slug' => $item['slug']]) }}">{{ $item['name'] }}</a></h2>
                                <p class="cart-total tabular-nums">{{ $item['total_cents'] === null ? 'Importe no disponible' : $money($item['total_cents']).' '.$data['currency'] }}<small>Importe de la línea · IVA incluido</small></p>
                            </div>
                            <form method="POST" action="{{ route('cart.update') }}" class="cart-form" data-submit-feedback>
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                <div class="cart-qty">
                                    <label for="cantidad-{{ $item['product_id'] }}">Cantidad de {{ $item['name'] }}</label>
                                    <input id="cantidad-{{ $item['product_id'] }}" @if($hasQuantityError) aria-invalid="true" aria-describedby="cantidad-error-{{ $item['product_id'] }}" @endif name="quantity" type="number" min="1" step="1" required value="{{ $hasQuantityError && is_scalar(old('quantity')) ? old('quantity') : $item['quantity'] }}">
                                    @if($hasQuantityError)<p id="cantidad-error-{{ $item['product_id'] }}" class="mt-2 text-sm text-red-800">{{ $errors->first('quantity') }}</p>@endif
                                </div>
                                <button type="submit" name="action" value="put" class="button-secondary">Actualizar</button>
                                <button type="submit" name="action" value="remove" formnovalidate aria-label="Quitar {{ $item['name'] }} del carrito" class="cart-remove">Quitar</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </section>
            <aside aria-label="Resumen del carrito" class="purchase-summary min-w-0 self-start">
                <h2 class="text-xl font-semibold">Resumen</h2>
                @if($data['coupon']['code'] ?? null)
                    <p class="mt-3 text-sm font-medium">Cupón {{ $data['coupon']['code'] }}</p>
                    <p class="mt-2 text-sm text-slate-600">Cupón válido una vez por número de contacto</p>
                @else
                    <p class="mt-3 text-sm text-slate-600">Puedes aplicar un cupón al revisar la compra.</p>
                @endif
                @if($data['total_cents'] !== null)
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex flex-wrap justify-between gap-2"><dt>Subtotal sin IVA</dt><dd class="break-all tabular-nums">{{ $money($data['subtotal_cents']) }} {{ $data['currency'] }}</dd></div>
                        @if(($data['discount_cents'] ?? 0) > 0)
                            <div class="flex flex-wrap justify-between gap-2"><dt>Descuento sin IVA</dt><dd class="break-all tabular-nums">−{{ $money($data['discount_cents']) }} {{ $data['currency'] }}</dd></div>
                        @endif
                        <div class="flex flex-wrap justify-between gap-2"><dt>IVA</dt><dd class="break-all tabular-nums">{{ $money($data['tax_cents']) }} {{ $data['currency'] }}</dd></div>
                        <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-3 text-lg font-semibold"><dt>Total</dt><dd class="break-all tabular-nums">{{ $money($data['total_cents']) }} {{ $data['currency'] }}</dd></div>
                    </dl>
                    @if($data['coupon']['is_capped'] ?? false)
                        <x-notice label="Cupón"><p>El descuento fue recortado de {{ $money($data['coupon']['requested_discount_minor']) }} a {{ $money($data['coupon']['applied_discount_minor']) }} {{ $data['currency'] }} para respetar el máximo del 90% de la base elegible. Revisa y acepta el total antes de pagar.</p></x-notice>
                    @endif
                    <a href="{{ route('checkout.show') }}" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-md bg-blue-800 px-4 py-3 font-medium text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Continuar compra</a>
                @else
                    <x-notice type="error" label="Tu carrito"><p>{{ $data['error'] }}</p></x-notice>
                @endif
                <p class="mt-5 text-sm leading-6 text-slate-600">Solo recogida en local. Espera el segundo correo que indica cuándo acudir; no se promete disponibilidad inmediata.</p>
                <a href="{{ route('store.information') }}" class="mt-3 inline-flex min-h-11 items-center text-sm text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Información de recogida</a>
            </aside>
        </div>
    @endif
</x-layout>
