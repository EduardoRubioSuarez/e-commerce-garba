<x-layout :title="$coupon->exists ? 'Editar cupón' : 'Crear cupón'">
    @php
        $decimal = fn (int $value): string => intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
        $kindOptions = ['percentage' => 'Porcentaje', 'fixed' => 'Importe fijo'];
        $scopeOptions = ['all' => 'Todos los productos', 'selected' => 'Solo productos seleccionados'];
        $text = fn (string $name, string $label, string $type, $value, ?string $help = null, string $extra = '', bool $required = true) => compact('name', 'label', 'type', 'value', 'help', 'extra', 'required');
        $fields = [
            'code' => $text('code', 'Código', 'text', $coupon->code, 'Lo que escribirá el cliente. Máximo 50 caracteres.', 'maxlength="50"'),
            'value' => $text('value', 'Valor', 'text', $coupon->exists ? $decimal($coupon->value) : '10', 'Porcentaje (máximo 90) o importe sin IVA.', 'inputmode="decimal"'),
            'currency' => $text('currency', 'Moneda', 'text', $coupon->currency ?? $settings->currency),
            'minimum_base' => $text('minimum_base', 'Compra mínima sin IVA', 'text', $decimal($coupon->minimum_base_minor ?? 0), 'Con 0 no hay mínimo.', 'inputmode="decimal"'),
            'max_uses' => $text('max_uses', 'Usos máximos', 'number', $coupon->max_uses ?? 50, 'Veces que se puede usar en total.', 'min="1" max="4294967295"'),
            'starts_at' => $text('starts_at', 'Inicio', 'datetime-local', $coupon->starts_at?->setTimezone($settings->timezone)->format('Y-m-d\TH:i'), 'Opcional. Se incluye este momento.', '', false),
            'ends_at' => $text('ends_at', 'Fin', 'datetime-local', $coupon->ends_at?->setTimezone($settings->timezone)->format('Y-m-d\TH:i'), 'Opcional. El cupón deja de servir en este momento.', '', false),
        ];
    @endphp
    <x-admin.page-header :title="$coupon->exists ? 'Editar cupón' : 'Crear cupón'" subtitle="Este panel no activa todavía el uso del cupón en el pago. Para conservar su historial, desactívalo en lugar de borrarlo." />
    @if($errors->any()) <div class="mt-6"><x-notice type="error" label="Cupón"><p>Revisa los campos señalados.</p></x-notice></div> @endif

    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="mt-8 grid max-w-3xl gap-6">
        @csrf
        @if($coupon->exists) @method('PUT') @endif

        <section class="panel" aria-labelledby="sec-descuento">
            <h2 id="sec-descuento" class="panel-title">Descuento</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    @php($f = $fields['code'])
                    <label for="code" class="field-label">{{ $f['label'] }}</label>
                    <input id="code" name="code" type="text" value="{{ old('code', $f['value']) }}" required {!! $f['extra'] !!} @if($errors->has('code')) aria-invalid="true" aria-describedby="code-error" @endif class="input font-mono uppercase tracking-wide">
                    <p class="field-help">{{ $f['help'] }}</p>
                    @error('code') <p id="code-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="kind" class="field-label">Tipo</label>
                    <select id="kind" name="kind" required @if($errors->has('kind')) aria-invalid="true" aria-describedby="kind-error" @endif class="input">
                        @foreach($kindOptions as $key => $label) <option value="{{ $key }}" @selected(old('kind', $coupon->kind?->value ?? 'percentage') === $key)>{{ $label }}</option> @endforeach
                    </select>
                    @error('kind') <p id="kind-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                @foreach(['value', 'currency'] as $name)
                    @php($f = $fields[$name])
                    <div>
                        <label for="{{ $name }}" class="field-label">{{ $f['label'] }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="text" value="{{ old($name, $f['value']) }}" required {!! $f['extra'] !!} @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif class="input tabular-nums">
                        @if($f['help'])<p class="field-help">{{ $f['help'] }}</p>@endif
                        @error($name) <p id="{{ $name }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel" aria-labelledby="sec-condiciones">
            <h2 id="sec-condiciones" class="panel-title">Condiciones de uso</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach(['minimum_base', 'max_uses'] as $name)
                    @php($f = $fields[$name])
                    <div>
                        <label for="{{ $name }}" class="field-label">{{ $f['label'] }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $f['type'] }}" value="{{ old($name, $f['value']) }}" required {!! $f['extra'] !!} @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif class="input tabular-nums">
                        <p class="field-help">{{ $f['help'] }}</p>
                        @error($name) <p id="{{ $name }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label for="scope" class="field-label">Productos a los que aplica</label>
                    <select id="scope" name="scope" required @if($errors->has('scope')) aria-invalid="true" aria-describedby="scope-error" @endif class="input">
                        @foreach($scopeOptions as $key => $label) <option value="{{ $key }}" @selected(old('scope', $coupon->scope?->value ?? 'all') === $key)>{{ $label }}</option> @endforeach
                    </select>
                    @error('scope') <p id="scope-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <p class="field-label" id="productos-legend">Productos seleccionados</p>
                    <p class="field-help">Obligatorio solo si elegiste "Solo productos seleccionados".</p>
                    <div class="mt-3 grid max-h-64 gap-x-4 overflow-y-auto rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2" role="group" aria-labelledby="productos-legend">
                        @foreach($products as $product)
                            <label class="flex min-h-11 items-center gap-3 break-words"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, old('product_ids', $coupon->products->modelKeys()))) class="h-5 w-5 shrink-0 accent-blue-800"><span>{{ $product->name }}</span></label>
                        @endforeach
                    </div>
                    @foreach($errors->get('product_ids*') as $messages) @foreach($messages as $message) <p role="alert" class="field-error">{{ $message }}</p> @endforeach @endforeach
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="sec-vigencia">
            <h2 id="sec-vigencia" class="panel-title">Vigencia</h2>
            <p class="field-help">Las fechas usan la zona horaria {{ $settings->timezone }}.</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                @foreach(['starts_at', 'ends_at'] as $name)
                    @php($f = $fields[$name])
                    <div>
                        <label for="{{ $name }}" class="field-label">{{ $f['label'] }}</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="datetime-local" value="{{ old($name, $f['value']) }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif class="input">
                        <p class="field-help">{{ $f['help'] }}</p>
                        @error($name) <p id="{{ $name }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel" aria-labelledby="sec-opciones">
            <h2 id="sec-opciones" class="panel-title">Opciones</h2>
            <div class="mt-2 divide-y divide-slate-200">
                @foreach(['applies_to_installers' => ['Permitir tarifa de instalador', 'Los instaladores también podrán usar este cupón.'], 'applies_to_manual_prices' => ['Permitir productos con precio manual', 'Incluye los productos a los que les pusiste un precio a mano.'], 'is_active' => ['Cupón activo', 'Si lo desactivas, deja de poder usarse pero se conserva su historial.']] as $name => [$label, $help])
                    <input type="hidden" name="{{ $name }}" value="0">
                    <label class="toggle-row cursor-pointer">
                        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $coupon->getAttribute($name) ?? ($name === 'is_active')))>
                        <span><span class="block font-medium">{{ $label }}</span><span class="field-help block">{{ $help }}</span></span>
                    </label>
                    @error($name) <p role="alert" class="field-error">{{ $message }}</p> @enderror
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Guardar cupón</button>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</x-layout>
