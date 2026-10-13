<x-layout title="Pedidos">
    @php
        $money = fn (int $cents): string => \App\Support\Money::format($cents);
        $filters = ['all' => 'Todos', 'paid' => 'Pagados', 'pending' => 'Pendientes', 'closed_unpaid' => 'Cerrados sin pago'];
    @endphp
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-semibold">Pedidos</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $orders->total() }} {{ $orders->total() === 1 ? 'pedido' : 'pedidos' }}{{ $status === 'paid' ? ', incluye los que tienen reembolsos o incidencias' : '' }}</p>
        </div>
    </header>
    <nav aria-label="Filtrar por estado" class="mt-6 flex flex-wrap gap-2">
        @foreach($filters as $value => $label)
            <a href="{{ route('admin.orders.index', ['status' => $value]) }}" @if($status === $value) aria-current="true" @endif
               class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold ring-1 ring-inset transition-colors {{ $status === $value ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <ul class="mt-6 grid gap-3">
        @forelse($orders as $order)
            @php
                $statusLabel = match($order->status) { \App\Enums\OrderStatus::Paid => 'Pagado', \App\Enums\OrderStatus::ClosedUnpaid => 'Cerrado sin pago', default => 'Pendiente' };
                $statusClass = match($order->status) { \App\Enums\OrderStatus::Paid => 'bg-emerald-50 text-emerald-800 ring-emerald-200', \App\Enums\OrderStatus::ClosedUnpaid => 'bg-slate-100 text-slate-700 ring-slate-200', default => 'bg-amber-50 text-amber-800 ring-amber-200' };
                $items = (int) $order->items_count;
            @endphp
            <li class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 transition-colors hover:border-slate-300 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-6">
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold">{{ $order->contact_name ?: 'Cliente sin nombre' }}</p>
                    <p class="truncate text-sm text-slate-600">{{ $order->contact_email }}</p>
                    <p class="mt-2 text-sm text-slate-500">
                        <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('Y-m-d H:i') }}</time>
                        <span class="mx-1" aria-hidden="true">/</span>{{ $items }} {{ $items === 1 ? 'producto' : 'productos' }}
                        <span class="mx-1" aria-hidden="true">/</span><span class="font-mono text-xs" title="{{ $order->reference }}">#{{ \Illuminate\Support\Str::substr($order->reference, 0, 8) }}</span>
                    </p>
                </div>
                <div class="sm:text-right">
                    <p class="text-xl font-semibold tabular-nums">{{ $money($order->total_cents) }}</p>
                    <p class="text-xs text-slate-500">{{ $order->currency }}, IVA incluido</p>
                    <span class="mt-2 inline-block rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <a class="btn btn-primary" href="{{ route('admin.orders.show', ['order' => $order->reference]) }}">Ver detalles</a>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                <p class="font-medium text-slate-900">No hay pedidos con este filtro.</p>
                <p class="mt-1 text-sm">Prueba con "Todos" para ver cada pedido registrado.</p>
            </li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $orders->links() }}</div>
</x-layout>
