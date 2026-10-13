@props(['step' => 1])
<nav aria-label="Progreso de compra" class="checkout-progress">
    <ol>
        @foreach(['Carrito', 'Datos', 'Revisión', 'Pago'] as $label)
            <li class="{{ $loop->iteration < $step ? 'is-complete' : '' }} {{ $loop->iteration === $step ? 'is-current' : '' }}" @if($loop->iteration === $step) aria-current="step" @endif>
                <span class="step-number" aria-hidden="true">{{ $loop->iteration }}</span>
                <span>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
