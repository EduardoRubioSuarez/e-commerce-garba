<x-layout :title="$step === 'contact' ? 'Datos de contacto' : 'Revisa tu compra'">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
    @endphp
    <x-checkout-progress :step="$step === 'contact' ? 2 : 3" />
    @if($step === 'contact')
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Datos de contacto</h1>
        <p class="mt-3 max-w-2xl leading-7 text-slate-600">Compra como invitado. Usaremos tu nombre, correo y teléfono para atender el pedido y la recogida.</p>
        <div class="checkout-contact">
        <form method="POST" action="{{ route('checkout.review') }}" class="purchase-panel space-y-5" data-submit-feedback>
            @csrf
            <div>
                <label for="nombre" class="block text-sm font-medium">Nombre completo</label>
                <input id="nombre" name="name" type="text" autocomplete="name" maxlength="150" required value="{{ old('name', $contact['name'] ?? '') }}" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-blue-700" @error('name') aria-invalid="true" aria-describedby="field-name-error" @enderror>
                @error('name')<p id="field-name-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="correo" class="block text-sm font-medium">Correo electrónico</label>
                <input id="correo" name="email" type="email" autocomplete="email" maxlength="255" required value="{{ old('email', $contact['email'] ?? '') }}" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-blue-700" @error('email') aria-invalid="true" aria-describedby="field-email-error" @enderror>
                @error('email')<p id="field-email-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="pais" class="block text-sm font-medium">País del teléfono</label>
                <select id="pais" name="country" required class="mt-2 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-blue-700" @error('country') aria-invalid="true" aria-describedby="field-country-error" @enderror>
                    <option value="">Selecciona el país</option>
                    <option value="MX" @selected(old('country', isset($contact['phone']) ? 'MX' : '') === 'MX')>México (+52)</option>
                </select>
                @error('country')<p id="field-country-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="telefono" class="block text-sm font-medium">Teléfono</label>
                <input id="telefono" name="phone" type="tel" autocomplete="tel" aria-describedby="{{ $errors->has('phone') ? 'ayuda-telefono field-phone-error' : 'ayuda-telefono' }}" maxlength="64" required value="{{ old('phone', $contact['phone'] ?? '') }}" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-blue-700" @error('phone') aria-invalid="true" @enderror>
                @error('phone')<p id="field-phone-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                <p id="ayuda-telefono" class="mt-2 text-sm text-slate-600">10 dígitos, o +52 seguido de 10 dígitos. Ejemplo: +52 614 123 4567.</p>
            </div>
            <p class="text-sm leading-6 text-slate-600">Solo recogida en local. En el siguiente paso podrás revisar el contacto y el importe antes de confirmar.</p>
            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" class="min-h-11 rounded-md bg-blue-800 px-5 py-3 font-medium text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Revisar compra</button>
                <a href="{{ route('cart.show') }}" class="inline-flex min-h-11 items-center text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Volver al carrito</a>
            </div>
        </form>
        <aside class="purchase-summary contact-guidance" aria-label="Cómo recibirás tu equipo"><h2>Tu compra, paso a paso</h2><ol><li><strong>1. Revisa tu pedido</strong>Confirma tus datos y el total con IVA antes de pagar.</li><li><strong>2. Paga en la tienda</strong>Completa el formulario seguro de tarjeta.</li><li><strong>3. Recoge en local</strong>Espera el segundo correo con el aviso de recogida antes de acudir.</li></ol><a href="{{ route('store.information') }}" class="text-link mt-4">Información de recogida</a></aside></div>
    @else
        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Revisa tu compra</h1>
        <p class="mt-3 max-w-2xl leading-7 text-slate-600">Comprueba los artículos, el contacto y el total con IVA antes de confirmar el pedido.</p>
        @if($data['changed'])
            <x-notice label="Tu pedido"><p>La cotización cambió. Revisa el total actualizado y confirma nuevamente.</p></x-notice>
        @endif
        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
            <div class="min-w-0">
                <section aria-labelledby="contacto-revision" class="border-y border-slate-200 py-6">
                    <h2 id="contacto-revision" class="text-xl font-semibold">Contacto para la compra</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach(['name' => 'Nombre', 'email' => 'Correo', 'phone' => 'Teléfono'] as $key => $label)
                            <div><dt class="font-medium">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-600">{{ $data['contact'][$key] }}</dd></div>
                        @endforeach
                    </dl>
                    <a href="{{ route('checkout.show') }}" class="mt-3 inline-flex min-h-11 items-center text-sm text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Cambiar contacto</a>
                </section>
                <section aria-labelledby="articulos-revision" class="mt-6">
                    <h2 id="articulos-revision" class="text-xl font-semibold">Artículos</h2>
                    <ul class="mt-4 divide-y divide-slate-200">
                        @foreach($data['quote']['lines'] as $line)
                            <li class="flex min-w-0 flex-wrap justify-between gap-3 py-4">
                                <div class="min-w-0"><p class="break-words font-medium">{{ $line['name'] }}</p><p class="mt-1 text-sm text-slate-600">Cantidad: {{ $line['quantity'] }}</p></div>
                                <p class="break-all font-semibold tabular-nums">{{ $money($line['total_cents']) }} {{ $data['quote']['currency'] }}</p>
                                @if($line['discount_cents'] > 0)
                                    <p class="w-full text-sm text-slate-600">Descuento sin IVA: {{ $money($line['discount_cents']) }} {{ $data['quote']['currency'] }}. IVA calculado después del descuento.</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
            <aside aria-label="Confirmación del pedido" class="purchase-summary min-w-0 self-start">
                <h2 class="text-xl font-semibold">Total de la compra</h2>
                <details class="mt-5" @if(($data['coupon_code'] ?? null) || (isset($errors) && $errors->has('coupon_code'))) open @endif>
                <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium text-blue-800">Tengo un cupón</summary>
                <form method="POST" action="{{ route('coupons.validate') }}" class="mt-3 space-y-3" data-submit-feedback>
                    @csrf
                    <input type="hidden" name="phone" value="{{ $data['contact']['phone'] }}" @error('phone') aria-invalid="true" aria-describedby="field-phone-error" @enderror>
                @error('phone')<p id="field-phone-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                    <input type="hidden" name="country" value="MX" @error('country') aria-invalid="true" aria-describedby="field-country-error" @enderror>
                    <label for="coupon-code" class="block text-sm font-medium">Cupón opcional</label>
                    <input id="coupon-code" name="coupon_code" maxlength="32" autocomplete="off" value="{{ old('coupon_code', $data['coupon_code'] ?? '') }}" class="min-h-11 w-full rounded-md border border-slate-300 px-3 py-2" @error('coupon_code') aria-invalid="true" aria-describedby="field-coupon_code-error" @enderror>
                @error('coupon_code')<p id="field-coupon_code-error" class="mt-2 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
                    <p class="text-sm text-slate-600">Cupón válido una vez por número de contacto</p>
                    <button type="submit" class="min-h-11 rounded-md border border-blue-800 px-4 py-2 text-blue-800">Aplicar cupón</button>
                    <button type="submit" name="coupon_code" value="" class="min-h-11 px-3 py-2 text-blue-800 underline">Quitar cupón</button>
                </form>
                </details>
                @if($data['quote']['coupon']['is_capped'] ?? false)
                    <x-notice label="Cupón"><p>El descuento fue recortado de {{ $money($data['quote']['coupon']['requested_discount_minor']) }} a {{ $money($data['quote']['coupon']['applied_discount_minor']) }} {{ $data['quote']['currency'] }} por el máximo del 90% de la base elegible. Revisa y acepta este total antes de pagar.</p></x-notice>
                @endif
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2"><dt>Subtotal sin IVA</dt><dd class="break-all tabular-nums">{{ $money($data['quote']['subtotal_cents']) }} {{ $data['quote']['currency'] }}</dd></div>
                    @if($data['quote']['discount_cents'] > 0)
                        <div class="flex flex-wrap justify-between gap-2"><dt>Descuento sin IVA</dt><dd class="break-all tabular-nums">−{{ $money($data['quote']['discount_cents']) }} {{ $data['quote']['currency'] }}</dd></div>
                    @endif
                    <div class="flex flex-wrap justify-between gap-2"><dt>IVA</dt><dd class="break-all tabular-nums">{{ $money($data['quote']['tax_cents']) }} {{ $data['quote']['currency'] }}</dd></div>
                    <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-3 text-lg font-semibold"><dt>Total</dt><dd class="break-all tabular-nums">{{ $money($data['quote']['total_cents']) }} {{ $data['quote']['currency'] }}</dd></div>
                </dl>
                <p class="mt-5 text-sm leading-6 text-slate-600">Recogida exclusiva en el local. Espera el segundo correo antes de acudir; el pago no implica disponibilidad inmediata.</p>
                <details class="mt-5 text-sm leading-6">
                    <summary class="min-h-11 cursor-pointer py-2 font-medium text-blue-800 focus-visible:outline-2 focus-visible:outline-blue-700">Condiciones de compra</summary>
                    <p class="mt-2 whitespace-pre-line break-words text-slate-600">{{ \App\Models\StoreSetting::current()->terms ?? config('store.information.terms') }}</p>
                </details>
                <a href="{{ route('store.information') }}" class="inline-flex min-h-11 items-center text-sm text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Privacidad y recogida</a>
                <form method="POST" action="{{ route('checkout.start') }}" class="mt-4" data-submit-feedback>
                    @csrf
                    <input type="hidden" name="review_key" value="{{ $data['review_key'] }}">
                    <input type="hidden" name="accepted_fingerprint" value="{{ $data['fingerprint'] }}">
                    <label class="flex min-h-11 items-start gap-3 text-sm leading-6">
                        <input type="checkbox" name="accept_terms" value="1" required class="mt-1 h-5 w-5 shrink-0 accent-blue-800 focus-visible:outline-2 focus-visible:outline-blue-700">
                        <span>Confirmo que el contacto y el total son correctos y acepto las condiciones de compra.</span>
                    </label>
                    <button type="submit" class="mt-5 min-h-11 w-full rounded-md bg-blue-800 px-5 py-3 font-medium text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700">Confirmar pedido</button>
                </form>
            </aside>
        </div>
    @endif
</x-layout>
