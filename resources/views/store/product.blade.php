<x-layout :title="$product['name']">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
    @endphp
    <nav aria-label="Ruta de navegación" class="mb-8 text-sm">
        <a href="{{ route('store.catalog') }}" class="inline-flex min-h-11 items-center text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">Volver al catálogo</a>
    </nav>
    <div class="grid min-w-0 gap-8 lg:grid-cols-2 lg:gap-12">
        <section aria-label="Fotografías del equipo" class="product-gallery min-w-0">
            @if(count($product['photos']) > 0)
                <div class="space-y-5">
                    @foreach($product['photos'] as $photo)
                        <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-white p-5">
                            <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" width="800" height="600" @if(!$loop->first) loading="lazy" @endif class="h-full w-full object-contain">
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex aspect-[4/3] items-center justify-center rounded-md border border-slate-200 bg-white p-5 text-sm text-slate-500">Sin fotografía</div>
            @endif
        </section>
        <div class="product-purchase min-w-0">
            <p class="eyebrow">Elige tu equipo</p>
            <a href="{{ route('store.catalog', ['category' => $product['category']['slug']]) }}" class="inline-flex min-h-11 max-w-full items-center break-words text-sm text-blue-800 underline focus-visible:outline-2 focus-visible:outline-blue-700">{{ $product['category']['name'] }}</a>
            @if($product['capacity_btu'])<span class="spec-chip">{{ number_format($product['capacity_btu'], 0, '.', ',') }} BTU</span>@endif
            <h1 class="mt-2 break-words text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">{{ $product['name'] }}</h1>
            @if($product['price'] !== null)
                <p class="mt-6 text-3xl font-semibold tabular-nums">{{ $money($product['price']['total_cents']) }} <span class="text-base font-normal">{{ $product['price']['currency'] }}</span></p>
                <p class="mt-2 text-sm text-slate-600">Precio por unidad · IVA incluido</p>
                @if($tier === \App\Enums\PriceTier::Installer)
                    <p class="mt-2 text-sm font-medium text-blue-800">Tarifa de instalador</p>
                @endif
            @else
                <p class="mt-6 text-lg font-medium text-slate-600">Precio no disponible</p>
            @endif
            @if($product['price'] !== null)
                <form method="POST" action="{{ route('cart.update') }}" class="mt-6 flex flex-wrap items-end gap-3" data-submit-feedback>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                    <input type="hidden" name="return_to" value="product">
                    <div>
                        <label for="cantidad" class="block text-sm font-medium">Cantidad</label>
                        <input id="cantidad" name="quantity" type="number" min="1" step="1" required value="{{ is_scalar(old('quantity', 1)) ? old('quantity', 1) : 1 }}" @error('quantity') aria-invalid="true" aria-describedby="cantidad-error" @enderror class="mt-2 min-h-11 w-28 max-w-full rounded-md border border-slate-300 bg-white px-3 py-2 focus:outline-2 focus:outline-blue-700">
                        @error('quantity')<p id="cantidad-error" class="mt-2 text-sm text-red-800">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="min-h-11 rounded-md bg-blue-800 px-5 py-3 font-semibold text-white hover:bg-blue-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-blue-700">Agregar al carrito</button>
                </form>
                <a href="{{ route('cart.show') }}" class="text-link mt-3">Ver carrito</a>
                <p class="mt-3 text-sm leading-6 text-slate-600">Solo recogida en local. Espera el segundo correo antes de acudir.</p>
            @endif
            @foreach(['description' => 'Descripción', 'specifications' => 'Características', 'equipment_contents' => 'Contenido del equipo'] as $field => $heading)
                @if($product[$field] !== '')
                    <details class="mt-4" @if($loop->first) open @endif>
                        <summary>{{ $heading }}</summary>
                        <p class="mt-3 whitespace-pre-line break-words leading-7 text-slate-600">{{ $product[$field] }}</p>
                    </details>
                @endif
            @endforeach
        </div>
    </div>
</x-layout>
