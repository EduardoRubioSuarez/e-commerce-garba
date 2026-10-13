<x-layout :title="$product->exists ? 'Editar producto' : 'Crear producto'">
    <x-admin.page-header :title="$product->exists ? 'Editar producto' : 'Crear producto'" :subtitle="$product->exists ? $product->name : 'Llena los datos del producto. Podrás agregar fotografías después de guardarlo.'" />
    @if($categories->isEmpty())
        <div class="panel mt-8 max-w-xl">
            <p class="text-slate-700">Primero crea una categoría para organizar tus productos.</p>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-primary mt-4">Crear categoría</a>
        </div>
    @else
        @php
            $defaultCost = $product->exists ? intdiv($product->base_cost_cents, 100).'.'.str_pad((string) ($product->base_cost_cents % 100), 2, '0', STR_PAD_LEFT) : '';
        @endphp
        <form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" class="mt-8 grid max-w-3xl gap-6">
            @csrf
            @if($product->exists) @method('PUT') @endif

            <section class="panel grid gap-5 sm:grid-cols-2">
                <h2 class="panel-title sm:col-span-full">Información básica</h2>
                <div class="sm:col-span-2">
                    <label for="name" class="field-label">Nombre</label>
                    <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="150" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="input">
                    @error('name') <p id="name-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="slug" class="field-label">Identificador del producto</label>
                    <input id="slug" name="slug" value="{{ old('slug', $product->slug) }}" required maxlength="150" aria-describedby="slug-help @error('slug') slug-error @enderror" @error('slug') aria-invalid="true" @enderror class="input">
                    <p id="slug-help" class="field-help">Es la parte del enlace del producto. Usa minúsculas, números y guiones; por ejemplo, minisplit-12000.</p>
                    @error('slug') <p id="slug-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="category_id" class="field-label">Categoría</label>
                    <select id="category_id" name="category_id" required @error('category_id') aria-invalid="true" aria-describedby="category-error" @enderror class="input">
                        <option value="">Selecciona una categoría</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p id="category-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="capacity_btu" class="field-label">Capacidad (BTU)</label>
                    <input id="capacity_btu" name="capacity_btu" type="number" inputmode="numeric" min="1000" max="200000" step="1" value="{{ old('capacity_btu', $product->capacity_btu) }}" aria-describedby="capacity-help @error('capacity_btu') capacity-error @enderror" @error('capacity_btu') aria-invalid="true" @enderror class="input">
                    <p id="capacity-help" class="field-help">Opcional. Permite filtrar el catálogo por capacidad; por ejemplo, 12000.</p>
                    @error('capacity_btu') <p id="capacity-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="base_cost" class="field-label">Costo de fábrica sin IVA ({{ $currency }})</label>
                    <input id="base_cost" name="base_cost" type="text" inputmode="decimal" value="{{ old('base_cost', $defaultCost) }}" required maxlength="13" aria-describedby="cost-help @error('base_cost') cost-error @enderror" @error('base_cost') aria-invalid="true" @enderror class="input tabular-nums">
                    <p id="cost-help" class="field-help">Solo lo ven los administradores. Usa punto decimal; por ejemplo, 1000.01.</p>
                    @error('base_cost') <p id="cost-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="panel grid gap-5">
                <h2 class="panel-title sm:col-span-full">Descripción para el cliente</h2>
                @foreach(['description' => 'Descripción', 'specifications' => 'Características técnicas', 'equipment_contents' => 'Contenido del equipo'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                        <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="10000" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror class="input">{{ old($field, $product->getAttribute($field)) }}</textarea>
                        @error($field) <p id="{{ $field }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </section>

            <section class="panel">
                <h2 class="panel-title sm:col-span-full">Estado del producto</h2>
                <div class="divide-y divide-slate-200">
                    @foreach(['is_active' => ['Activo', 'Si lo desactivas se conserva el registro pero deja de usarse.'], 'is_published' => ['Publicado', 'La tienda muestra únicamente productos activos y publicados.']] as $field => [$label, $help])
                        <input type="hidden" name="{{ $field }}" value="0">
                        <label for="{{ $field }}" class="toggle-row cursor-pointer">
                            <input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" @checked((bool) old($field, $product->getAttribute($field))) @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                            <span><span class="block font-medium">{{ $label }}</span><span class="field-help block">{{ $help }}</span></span>
                        </label>
                        @error($field) <p id="{{ $field }}-error" role="alert" class="field-error">{{ $message }}</p> @enderror
                    @endforeach
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Guardar producto</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    @endif
    @if($product->exists)
        @include('admin.products.photos')
    @endif
</x-layout>
