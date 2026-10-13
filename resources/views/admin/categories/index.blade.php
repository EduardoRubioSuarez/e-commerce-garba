<x-layout title="Categorías">
    <x-admin.page-header title="Categorías" subtitle="Las categorías agrupan tus productos en el catálogo.">
        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Productos</a>
    </x-admin.page-header>

    <section aria-labelledby="crear" class="panel mt-8 sm:p-8">
        <h2 id="crear" class="panel-title">Crear categoría</h2>
        @php $isCreating = old('_form', 'create') === 'create'; @endphp
        <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-5 grid gap-5 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="_form" value="create">
            @foreach(['name' => 'Nombre', 'slug' => 'Identificador'] as $field => $label)
                <div>
                    <label for="create-{{ $field }}" class="field-label">{{ $label }}</label>
                    <input id="create-{{ $field }}" name="{{ $field }}" value="{{ $isCreating ? old($field) : '' }}" required maxlength="150" @if($isCreating && $errors->has($field)) aria-invalid="true" aria-describedby="create-{{ $field }}-error" @endif class="input">
                    @if($field === 'slug')<p class="field-help">Es la parte del enlace de la categoría. Usa minúsculas, números y guiones; por ejemplo, minisplits.</p>@endif
                    @if($isCreating && $errors->has($field)) <p id="create-{{ $field }}-error" role="alert" class="field-error">{{ $errors->first($field) }}</p> @endif
                </div>
            @endforeach
            <div class="sm:col-span-2"><button type="submit" class="btn btn-primary">Crear categoría</button></div>
        </form>
    </section>

    <section aria-labelledby="editar" class="mt-10">
        <h2 id="editar" class="text-xl font-semibold">Categorías existentes</h2>
        <ul class="mt-4 grid gap-3">
            @forelse($categories as $category)
                @php $isEditing = (string) old('_form') === (string) $category->id; @endphp
                <li class="panel">
                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="{{ $category->id }}">
                        @foreach(['name' => 'Nombre', 'slug' => 'Identificador'] as $field => $label)
                            <div>
                                <label for="category-{{ $category->id }}-{{ $field }}" class="field-label">{{ $label }}</label>
                                <input id="category-{{ $category->id }}-{{ $field }}" name="{{ $field }}" value="{{ $isEditing ? old($field) : $category->getAttribute($field) }}" required maxlength="150" @if($isEditing && $errors->has($field)) aria-invalid="true" aria-describedby="category-{{ $category->id }}-{{ $field }}-error" @endif class="input">
                                @if($isEditing && $errors->has($field)) <p id="category-{{ $category->id }}-{{ $field }}-error" role="alert" class="field-error">{{ $errors->first($field) }}</p> @endif
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-secondary sm:mt-7">Guardar categoría</button>
                    </form>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                    <p class="font-medium text-slate-900">Todavía no hay categorías.</p>
                    <p class="mt-1 text-sm">Crea la primera con el formulario de arriba.</p>
                </li>
            @endforelse
        </ul>
        <div class="mt-6">{{ $categories->links() }}</div>
    </section>
</x-layout>
