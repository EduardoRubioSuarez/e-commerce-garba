<x-layout title="Cupones">
    @php
        $decimal = fn (int $value): string => intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    @endphp
    <x-admin.page-header title="Cupones" subtitle="Códigos de descuento para tus clientes. Los importes y mínimos se calculan sin IVA.">
        <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">Crear cupón</a>
    </x-admin.page-header>
    <p role="note" class="mt-6 rounded-lg bg-slate-100 p-4 text-sm leading-6 text-slate-700">Configurar un cupón no activa todavía su uso en el pago. Las fechas se muestran en la zona horaria {{ $settings->timezone }}.</p>
    <ul class="mt-6 grid gap-4">
        @forelse($coupons as $coupon)
            @php
                $isPercentage = $coupon->kind === \App\Enums\CouponKind::Percentage;
                $starts = $coupon->starts_at?->setTimezone($settings->timezone)->format('d/m/Y H:i');
                $ends = $coupon->ends_at?->setTimezone($settings->timezone)->format('d/m/Y H:i');
            @endphp
            <li class="panel">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="break-all font-mono text-xl font-semibold">{{ $coupon->code }}</h2>
                            <span class="badge {{ $coupon->is_active ? 'badge-ok' : 'badge-off' }}">{{ $coupon->is_active ? 'Activo' : 'Desactivado' }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-semibold tabular-nums">{{ $decimal($coupon->value) }}{{ $isPercentage ? ' %' : ' '.$coupon->currency }}<span class="ml-2 text-sm font-normal text-slate-600">{{ $isPercentage ? 'de descuento' : 'de descuento fijo, sin IVA' }}</span></p>
                    </div>
                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-secondary shrink-0" aria-label="Editar {{ $coupon->code }}">Editar</a>
                </div>
                <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-slate-500">Compra mínima (sin IVA)</dt><dd class="mt-0.5 font-medium tabular-nums">{{ $decimal($coupon->minimum_base_minor) }} {{ $coupon->currency }}</dd></div>
                    <div><dt class="text-slate-500">Usos máximos</dt><dd class="mt-0.5 font-medium tabular-nums">{{ $coupon->max_uses }}</dd></div>
                    <div><dt class="text-slate-500">Productos</dt><dd class="mt-0.5 break-words font-medium">{{ $coupon->scope === \App\Enums\CouponScope::All ? 'Todos' : $coupon->products->pluck('name')->implode(', ') }}</dd></div>
                    <div><dt class="text-slate-500">Quién puede usarlo</dt><dd class="mt-0.5 font-medium">{{ $coupon->applies_to_installers ? 'Clientes e instaladores' : 'Solo clientes (tarifa pública)' }}</dd></div>
                    <div><dt class="text-slate-500">Productos con precio manual</dt><dd class="mt-0.5 font-medium">{{ $coupon->applies_to_manual_prices ? 'Incluidos' : 'Excluidos' }}</dd></div>
                    <div><dt class="text-slate-500">Vigencia</dt><dd class="mt-0.5 font-medium">{{ $starts ?? 'Sin fecha de inicio' }} a {{ $ends ?? 'sin fecha de fin' }}</dd></div>
                </dl>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                <p class="font-medium text-slate-900">No hay cupones configurados.</p>
                <p class="mt-1 text-sm">Crea el primero para ofrecer un descuento.</p>
            </li>
        @endforelse
    </ul>
    <div class="mt-6">{{ $coupons->links() }}</div>
</x-layout>
