<x-layout title="Instaladores">
    <x-admin.page-header title="Instaladores" subtitle="Invita a los instaladores a activar su acceso. Todos comparten la misma tarifa de instalador." />

    <section aria-labelledby="invitar" class="panel mt-8 max-w-3xl">
        <h2 id="invitar" class="panel-title">Invitar a un instalador</h2>
        <p class="mt-1 text-sm text-slate-600">Le llegará un correo con un enlace para crear su contraseña.</p>
        <form method="POST" action="{{ route('admin.installers.store') }}" class="mt-5 grid gap-5 sm:grid-cols-2">
            @csrf
            <div>
                <label for="name" class="field-label">Nombre</label>
                <input id="name" name="name" required maxlength="150" value="{{ old('name') }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="input">
                @error('name') <p id="name-error" role="alert" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="field-label">Correo electrónico</label>
                <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror class="input">
                @error('email') <p id="email-error" role="alert" class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2"><button type="submit" class="btn btn-primary">Crear e invitar</button></div>
        </form>
    </section>

    <section aria-labelledby="lista" class="mt-10">
        <h2 id="lista" class="text-xl font-semibold">Instaladores registrados</h2>
        <ul class="mt-4 grid gap-3">
            @forelse($installers as $installer)
                @php
                    [$stateLabel, $stateHint, $stateClass] = $installer->suspended_at
                        ? ['Suspendido', 'No puede entrar a su cuenta.', 'badge-off']
                        : ($installer->isActive() ? ['Activo', 'Puede entrar y comprar con la tarifa de instalador.', 'badge-ok'] : ['Invitación pendiente', 'Aún no crea su contraseña.', 'badge-warn']);
                @endphp
                <li class="panel min-w-0">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="break-words text-lg font-semibold">{{ $installer->name }}</h3>
                                <span class="badge {{ $stateClass }}">{{ $stateLabel }}</span>
                            </div>
                            <p class="mt-1 break-all text-sm text-slate-600">{{ $installer->email }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $stateHint }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 sm:justify-end">
                            @if(!$installer->isActive() && !$installer->suspended_at)
                                <form method="POST" action="{{ route('admin.installers.resend', $installer) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary">Reenviar invitación</button>
                                </form>
                            @endif
                            @if($installer->suspended_at)
                                <form method="POST" action="{{ route('admin.installers.reactivate', $installer) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Activar acceso</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.installers.suspend', $installer) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary">Suspender acceso</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <details class="mt-4 border-t border-slate-100 pt-3 text-sm">
                        <summary class="cursor-pointer font-medium text-red-800 underline underline-offset-2">Eliminar instalador</summary>
                        @if($installer->orders_count > 0)
                            <p class="mt-3 max-w-xl text-slate-600">Tiene {{ $installer->orders_count }} {{ $installer->orders_count === 1 ? 'pedido' : 'pedidos' }}, así que no se puede eliminar sin perder el historial. Suspende su acceso en su lugar.</p>
                        @else
                            <form method="POST" action="{{ route('admin.installers.destroy', $installer) }}" class="mt-3 grid gap-3">
                                @csrf @method('DELETE')
                                <p class="max-w-xl text-slate-600">Se borra la cuenta y sus invitaciones. No se puede deshacer.</p>
                                <button type="submit" class="btn btn-danger justify-self-start">Eliminar a {{ $installer->name }}</button>
                            </form>
                        @endif
                    </details>
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-slate-600">
                    <p class="font-medium text-slate-900">No hay instaladores registrados.</p>
                    <p class="mt-1 text-sm">Invita al primero con el formulario de arriba.</p>
                </li>
            @endforelse
        </ul>
        <div class="mt-6">{{ $installers->links() }}</div>
    </section>
</x-layout>
