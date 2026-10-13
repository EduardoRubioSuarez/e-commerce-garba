<x-layout title="Productos">
    <x-admin.page-header title="Productos" subtitle="El catálogo de tu tienda. Los costos son privados y se muestran sin IVA.">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Categorías</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Crear producto</a>
    </x-admin.page-header>
    @if($products->isEmpty())
        <div class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
            <p class="font-medium text-slate-900">Todavía no hay productos.</p>
            <p class="mt-1 text-sm">Crea una categoría y después tu primer producto.</p>
        </div>
    @else
        <div class="mt-8 overflow-x-auto rounded-lg border border-slate-200 bg-white" tabindex="0" role="region" aria-label="Listado de productos">
            <table class="w-full min-w-xl text-left text-sm">
                <caption class="sr-only">Catálogo administrable; costos privados sin IVA</caption>
                <thead class="bg-slate-50 text-slate-600">
                    <tr><th scope="col" class="px-5 py-3 font-semibold">Producto</th><th scope="col" class="px-5 py-3 font-semibold">Categoría</th><th scope="col" class="px-5 py-3 text-right font-semibold">Costo sin IVA ({{ $currency }})</th><th scope="col" class="px-5 py-3 font-semibold">Estado</th><th scope="col" class="px-5 py-3"><span class="sr-only">Acción</span></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($products as $product)
                        <tr class="hover:bg-slate-50">
                            <th scope="row" class="max-w-64 break-words px-5 py-4 font-semibold">{{ $product->name }}</th>
                            <td class="max-w-48 break-words px-5 py-4 text-slate-700">{{ $product->category->name }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-right tabular-nums">{{ \App\Support\Money::format($product->base_cost_cents) }}</td>
                            <td class="px-5 py-4">
                                @if(! $product->is_active)<span class="badge badge-off">Desactivado</span>
                                @elseif($product->is_published)<span class="badge badge-ok">Publicado</span>
                                @else<span class="badge badge-warn">Borrador</span>@endif
                            </td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('admin.products.edit', $product) }}" aria-label="Editar {{ $product->name }}" class="btn btn-secondary">Editar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layout>
