@php
    $content = match((int) $code) {
        403 => ['No tienes acceso a esta página', 'Puedes iniciar sesión con tu cuenta o continuar explorando los equipos.'],
        404 => ['No encontramos esta página', 'El enlace puede haber cambiado o el equipo ya no está disponible.'],
        419 => ['Tu sesión venció', 'Abre nuevamente la página antes de enviar el formulario. Si estabas pagando, revisa el pedido antes de volver a intentarlo.'],
        429 => ['Necesitamos una pausa', 'Se hicieron varias solicitudes seguidas. Espera unos minutos antes de volver a intentarlo.'],
        503 => ['Estamos temporalmente fuera de servicio', 'Vuelve a intentarlo en unos minutos. Si estabas pagando, conserva la referencia y consulta el pedido antes de iniciar otra compra.'],
        default => ['No pudimos cargar esta página', 'Ocurrió un problema temporal. Si estabas pagando, consulta el pedido antes de iniciar otra compra.'],
    };
@endphp
<x-layout :title="$content[0]">
    <section class="error-state" aria-labelledby="error-title">
        <p class="eyebrow">Garba · {{ $code }}</p>
        <h1 id="error-title">{{ $content[0] }}</h1>
        <p class="error-description">{{ $content[1] }}</p>
        <div class="error-actions">
            <a href="{{ route('store.catalog') }}" class="button-primary">Volver al catálogo</a>
            @if((int) $code === 403)
                <a href="{{ route('login') }}" class="button-secondary">Iniciar sesión</a>
            @endif
            <a href="{{ route('store.information') }}" class="text-link">Información y contacto</a>
        </div>
    </section>
</x-layout>
