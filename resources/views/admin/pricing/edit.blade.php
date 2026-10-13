<x-layout title="Precios">
    @php
        $decimal = fn (int $value): string => intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
        $pricingProduct = (string) old('_pricing_product', '');
        $globalErrors = $pricingProduct === '';
    @endphp
    <x-admin.page-header title="Precios" subtitle="El precio de venta se calcula sumando un porcentaje al costo de fábrica. Si un producto necesita un precio distinto, captura una excepción sin IVA: se conserva aunque cambies el costo o los porcentajes." />

    <section class="panel mt-8 sm:p-8" aria-labelledby="reglas">
        <h2 id="reglas" class="panel-title">Reglas generales</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">Porcentajes con hasta dos decimales. Moneda: {{ $settings->currency }}. Se aplican a las nuevas cotizaciones; las excepciones manuales no cambian.</p>
        <form method="POST" action="{{ route('admin.pricing.update') }}" class="mt-6">
            @csrf @method('PUT')
            <input type="hidden" name="page" value="{{ $products->currentPage() }}">
            @if($globalErrors && $errors->any())
                <x-notice type="error" label="Precios"><p>Revisa los porcentajes indicados. No se guardaron los cambios.</p></x-notice>
            @endif
            <div class="grid gap-5 sm:grid-cols-3">
                @foreach(['public_markup' => ['Aumento público (%)', $settings->public_markup_basis_points, 'Sobre el costo, para clientes.'], 'installer_markup' => ['Aumento instalador (%)', $settings->installer_markup_basis_points, 'Sobre el costo, para instaladores.'], 'tax_rate' => ['IVA (%)', $settings->tax_basis_points, 'Se suma al precio de venta.']] as $field => [$label, $value, $help])
                    <div>
                        <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="text" inputmode="decimal" required value="{{ $globalErrors ? old($field, $decimal($value)) : $decimal($value) }}" aria-describedby="{{ $field }}-help {{ $field }}-error" @if($globalErrors && $errors->has($field)) aria-invalid="true" @endif class="input tabular-nums">
                        <p id="{{ $field }}-help" class="field-help">{{ $help }}</p>
                        <p id="{{ $field }}-error" class="field-error">{{ $globalErrors ? $errors->first($field) : '' }}</p>
                    </div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary mt-4">Guardar porcentajes</button>
        </form>
    </section>

    <section class="mt-10" aria-labelledby="excepciones">
        <h2 id="excepciones" class="text-xl font-semibold">Precios por producto</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Los montos son por una unidad. Si dejas la excepción vacía se usa el precio automático de esa tarifa. Guarda los cambios para actualizar la vista previa.</p>
        @if($products->isEmpty())
            <p class="panel mt-5">Todavía no hay productos. <a href="{{ route('admin.products.create') }}" class="font-medium text-blue-900 underline">Crear producto</a>.</p>
        @endif
        <div class="mt-6 grid gap-6">
            @foreach($products as $product)
                @php $productErrors = $pricingProduct === (string) $product->id; @endphp
                <article class="panel sm:p-8" aria-labelledby="producto-{{ $product->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 id="producto-{{ $product->id }}" class="text-lg font-semibold">{{ $product->name }}</h3>
                                <span class="badge {{ $product->is_published ? 'badge-ok' : 'badge-off' }}">{{ $product->is_published ? 'Publicado' : 'Borrador' }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">Costo de fábrica (privado, sin IVA): <span class="font-medium tabular-nums text-slate-900">{{ $decimal($product->base_cost_cents) }} {{ $settings->currency }}</span></p>
                        </div>
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-secondary">Editar producto</a>
                    </div>
                    <form method="POST" action="{{ route('admin.product-pricing.update', $product) }}" class="mt-6">
                        @csrf @method('PUT')
                        <input type="hidden" name="page" value="{{ $products->currentPage() }}">
                        <input type="hidden" name="_pricing_product" value="{{ $product->id }}">
                        @if($productErrors && $errors->any()) <x-notice type="error" label="Precios"><p>Revisa los precios indicados. No se guardaron los cambios.</p></x-notice> @endif
                        <div class="grid gap-6 md:grid-cols-2">
                            @foreach(['public' => 'Público', 'installer' => 'Instalador'] as $tier => $label)
                                @php
                                    $preview = $previews[$product->id][$tier];
                                    $override = $product->priceOverrides->first(fn (\App\Models\PriceOverride $price): bool => $price->tier->value === $tier);
                                    $field = $tier.'_price';
                                    $inputId = $field.'-'.$product->id;
                                    $current = $override === null ? '' : $decimal($override->base_price_cents);
                                @endphp
                                <div class="rounded-lg bg-slate-50 p-5">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="font-semibold">Tarifa {{ strtolower($label) }}</h4>
                                        @if($preview !== null)<span class="badge {{ $preview->priceSource === 'manual' ? 'badge-warn' : 'badge-off' }}">{{ $preview->priceSource === 'manual' ? 'Origen: Manual' : 'Origen: Automático' }}</span>@endif
                                    </div>
                                    @if($preview !== null)
                                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                            <div><dt class="text-slate-600">Sin IVA</dt><dd class="mt-0.5 font-medium tabular-nums">{{ $decimal($preview->unitBaseCents) }} {{ $settings->currency }}</dd></div>
                                            <div><dt class="text-slate-600">Con IVA, lo que paga el cliente</dt><dd class="mt-0.5 text-lg font-semibold tabular-nums">{{ $decimal($preview->totalCents) }} {{ $settings->currency }}</dd></div>
                                        </dl>
                                    @else
                                        <p class="mt-4 text-sm text-red-800">Precio inválido. Revisa costo, excepción y porcentajes antes de publicar.</p>
                                    @endif
                                    <label for="{{ $inputId }}" class="field-label mt-5">Excepción {{ strtolower($label) }} sin IVA ({{ $settings->currency }})</label>
                                    <input id="{{ $inputId }}" name="{{ $field }}" type="text" inputmode="decimal" value="{{ $productErrors ? old($field, $current) : $current }}" aria-describedby="{{ $inputId }}-help {{ $inputId }}-error" @if($productErrors && $errors->has($field)) aria-invalid="true" @endif class="input tabular-nums">
                                    <p id="{{ $inputId }}-help" class="field-help">Vacío: automático. Importe positivo con punto y hasta dos decimales.</p>
                                    <p id="{{ $inputId }}-error" class="field-error">{{ $productErrors ? $errors->first($field) : '' }}</p>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary mt-5">Guardar precios de este producto</button>
                    </form>
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </section>
</x-layout>
