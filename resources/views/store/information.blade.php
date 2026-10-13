<x-layout title="Información de compra y recogida">
    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Compra y recogida</h1>
    <div class="mt-8 grid gap-10 md:grid-cols-2">
        <section aria-labelledby="local">
            <h2 id="local" class="text-xl font-semibold">Solo recogida en local</h2>
            <p class="mt-3 leading-7 text-slate-600">Espera el segundo correo que indica cuándo acudir. El pago no implica que puedas recoger de inmediato.</p>
            <dl class="mt-5 space-y-4">
                @foreach(['address' => 'Dirección', 'hours' => 'Horario'] as $key => $label)
                    <div><dt class="text-sm font-medium">{{ $label }}</dt><dd class="mt-1 whitespace-pre-line break-words leading-7 text-slate-600">{{ $data['pickup'][$key] ?: 'Pendiente de configurar' }}</dd></div>
                @endforeach
            </dl>
        </section>
        <section aria-labelledby="contacto">
            <h2 id="contacto" class="text-xl font-semibold">Contacto</h2>
            <dl class="mt-5 space-y-4">
                @foreach(['email' => 'Correo', 'phone' => 'Teléfono'] as $key => $label)
                    <div><dt class="text-sm font-medium">{{ $label }}</dt><dd class="mt-1 break-words leading-7 text-slate-600">{{ $data['contact'][$key] ?: 'Pendiente de configurar' }}</dd></div>
                @endforeach
            </dl>
        </section>
    </div>
    @foreach(['terms' => 'Condiciones de compra', 'privacy' => 'Privacidad'] as $key => $title)
        <section class="mt-10 border-t border-slate-200 pt-6" aria-labelledby="{{ $key }}">
            <h2 id="{{ $key }}" class="text-xl font-semibold">{{ $title }}</h2>
            <p class="mt-3 max-w-3xl whitespace-pre-line break-words leading-7 text-slate-600">{{ $data[$key] }}</p>
        </section>
    @endforeach
</x-layout>
