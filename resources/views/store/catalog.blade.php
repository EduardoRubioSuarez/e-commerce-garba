<x-layout title="Catálogo">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
        $hasFilters = $filters['q'] !== '' || $filters['category'] !== '' || $filters['capacity'] !== '';
        $railUrl = fn (string $slug): string => route('store.catalog', array_filter(['q' => $filters['q'], 'category' => $slug, 'capacity' => $filters['capacity']]));
        $capUrl = fn (string $key): string => route('store.catalog', array_filter(['q' => $filters['q'], 'category' => $filters['category'], 'capacity' => $key]));
        $totalVisible = $categories->sum(fn ($category) => (int) ($category->visible_count ?? 0));
    @endphp
    <div class="shop">
        <aside class="shop-rail" aria-label="Filtros">
            <section>
                <h2>Categoría</h2>
                <nav aria-label="Categorías">
                    <a href="{{ $railUrl('') }}" class="rail-opt" @if($filters['category'] === '') aria-current="true" @endif><span>Todos</span><small>{{ $totalVisible }}</small></a>
                    @foreach($categories as $category)
                        <a href="{{ $railUrl($category['slug']) }}" class="rail-opt" @if($filters['category'] === $category['slug']) aria-current="true" @endif><span>{{ $category['name'] }}</span><small>{{ $category->visible_count }}</small></a>
                    @endforeach
                </nav>
            </section>
            @if($capacities->isNotEmpty())
                <section>
                    <h2>Capacidad (BTU)</h2>
                    <nav aria-label="Capacidad">
                        <a href="{{ $capUrl('') }}" class="rail-opt" @if($filters['capacity'] === '') aria-current="true" @endif><span>Todas</span><small>{{ $totalVisible }}</small></a>
                        @foreach($capacities as $capacity)
                            <a href="{{ $capUrl($capacity['key']) }}" class="rail-opt" @if($filters['capacity'] === $capacity['key']) aria-current="true" @endif><span>{{ $capacity['label'] }}</span><small>{{ $capacity['count'] }}</small></a>
                        @endforeach
                    </nav>
                </section>
            @endif
        </aside>

        <section class="shop-main">
            <div class="shop-bar">
                <h1>Equipos de aire acondicionado</h1>
                <form method="GET" action="{{ route('store.catalog') }}" class="shop-search" role="search" aria-label="Buscar equipos">
                    @if($filters['category'] !== '')<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
                    @if($filters['capacity'] !== '')<input type="hidden" name="capacity" value="{{ $filters['capacity'] }}">@endif
                    <label for="busqueda" class="sr-only">Buscar equipo</label>
                    <input id="busqueda" type="search" name="q" value="{{ $filters['q'] }}" maxlength="120" placeholder="Buscar por nombre o descripción" autocomplete="off">
                    <button type="submit" class="button-primary">Buscar</button>
                </form>
            </div>
            <p class="shop-meta" aria-live="polite">
                <span>{{ $products->total() }} {{ $products->total() === 1 ? 'equipo' : 'equipos' }}. Precio final con IVA.@if($tier === \App\Enums\PriceTier::Installer) <strong>Estás viendo tu tarifa de instalador.</strong>@endif</span>
                @if($hasFilters)<a href="{{ route('store.catalog') }}" class="text-link" style="min-height:0">Limpiar filtros</a>@endif
            </p>

            @if($products->isEmpty())
                <div class="shop-empty">
                    <h2>No hay productos{{ $hasFilters ? ' que coincidan con tu búsqueda' : ' disponibles por ahora' }}.</h2>
                    <p>{{ $hasFilters ? 'Prueba otra búsqueda o quita los filtros.' : 'Vuelve a consultar el catálogo más adelante.' }}</p>
                    @if($hasFilters)<a href="{{ route('store.catalog') }}" class="button-secondary">Ver todos los equipos</a>@endif
                </div>
            @else
                <div class="shop-grid">
                    @foreach($products as $product)
                        <article class="shop-card" style="--i: {{ $loop->index }}" aria-labelledby="equipo-{{ $product['id'] }}">
                            <div class="shop-card-box">
                                <a href="{{ route('store.product', ['slug' => $product['slug']]) }}" class="shop-card-link">
                                    <div class="shop-photo">
                                        @if(count($product['photos']) > 0)
                                            <img src="{{ $product['photos'][0]['url'] }}" alt="{{ $product['photos'][0]['alt'] }}" loading="lazy" width="640" height="640">
                                        @else
                                            <span>Sin fotografía</span>
                                        @endif
                                        @if($product['capacity_btu'])<em class="shop-badge">{{ number_format($product['capacity_btu'] / 1000, $product['capacity_btu'] % 1000 === 0 ? 0 : 1) }}k BTU</em>@endif
                                    </div>
                                    <div class="shop-info">
                                        <p class="shop-cat">{{ $product['category']['name'] }}</p>
                                        <h2 id="equipo-{{ $product['id'] }}">{{ $product['name'] }}</h2>
                                    </div>
                                </a>
                                <div class="shop-foot">
                                    @if($product['price'] !== null)
                                        <p class="shop-price product-price"><span class="amount">{{ $money($product['price']['total_cents']) }}</span><span class="cur">{{ $product['price']['currency'] }}</span><small>IVA incluido, por unidad</small></p>
                                        <form method="POST" action="{{ route('cart.update') }}" class="quick-add" data-quick-add>
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="return_to" value="catalog">
                                            <button type="submit" aria-label="Agregar {{ $product['name'] }} al carrito">Agregar al carrito</button>
                                        </form>
                                    @else
                                        <p class="shop-price"><small>Precio no disponible</small></p>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
            <div class="mt-10">{{ $products->links() }}</div>
        </section>
    </div>
</x-layout>
